<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExportSqliteToZenodoCommand extends Command
{
    private const ENDPOINTS = [
        'manuscripts' => '/manuscripts/export_csv',
        'occurrences' => '/occurrences/export_csv',
        'persons' => '/persons/export_csv',
        'types' => '/types/export_csv',
    ];

    private const ZENODO_SANDBOX_API = 'https://sandbox.zenodo.org/api';

    private const ACCESS_RIGHT = 'restricted';

    protected $di = [];

    /**
     * ExportSqliteToZenodoCommand constructor.
     * @param HttpClientInterface $httpClient
     * @param string $zenodoAccessToken
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $zenodoAccessToken = ''
    ) {
        $this->di = [
            'HttpClient' => $httpClient,
            'ZenodoAccessToken' => $zenodoAccessToken,
        ];

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:export-sqlite-to-zenodo')
            ->setDescription('Export data to SQLite database and publish to Zenodo sandbox')
            ->setHelp('This command exports data from manuscripts, occurrences, persons, and types endpoints into a single SQLite database and publishes it to Zenodo sandbox.')
            ->addOption('zenodo-token', 't', InputOption::VALUE_OPTIONAL, 'Zenodo sandbox access token')
            ->addOption('deposition-id', 'd', InputOption::VALUE_OPTIONAL, 'Existing Zenodo deposition ID (if updating)')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Deposition title', 'SQLite Database Export')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Deposition description', 'Exported SQLite database from application')
            ->addOption('db-name', null, InputOption::VALUE_OPTIONAL, 'SQLite database filename', 'export_data.db')
            ->addOption('local-only', 'l', InputOption::VALUE_NONE, 'Only create database locally, do not upload to Zenodo')
            ->addOption('output-path', 'o', InputOption::VALUE_OPTIONAL, 'Output path for local database file', sys_get_temp_dir());
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $localOnly = $input->getOption('local-only');

        // Get Zenodo token only if not in local-only mode
        if (!$localOnly) {
            $token = $input->getOption('zenodo-token') ?: $this->di['ZenodoAccessToken'];
            if (empty($token)) {
                $io->error('Zenodo access token is required. Provide via --zenodo-token option or configure in services.');
                $io->note('Tip: Use --local-only to skip Zenodo upload and just create the database locally.');
                return Command::FAILURE;
            }
        } else {
            $token = null;
        }

        $io->title($localOnly ? 'Export SQLite Database Locally' : 'Export SQLite Database and Publish to Zenodo Sandbox');

        // Step 1: Create SQLite database
        $io->section('Step 1: Creating SQLite database');

        $dbFilename = $input->getOption('db-name');
        $outputPath = $input->getOption('output-path');
        $dbPath = rtrim($outputPath, '/') . '/' . $dbFilename;

        // Remove existing database if it exists
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        try {
            $db = new \SQLite3($dbPath);
            // Set UTF-8 encoding
            $db->exec('PRAGMA encoding = "UTF-8"');
            $io->success("Created SQLite database: {$dbFilename}");
        } catch (\Exception $e) {
            $io->error("Failed to create SQLite database: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 2: Export data from endpoints and populate tables
        $io->section('Step 2: Exporting data from endpoints');

        foreach (self::ENDPOINTS as $tableName => $path) {
            $io->text("Exporting {$tableName}...");

            try {
                $csvContent = $this->exportCsv($path);
                $this->createTableFromCsv($db, $tableName, $csvContent);
                $io->success("Exported {$tableName}");
            } catch (\Exception $e) {
                $io->error("Failed to export {$tableName}: " . $e->getMessage());
                $db->close();
                if (file_exists($dbPath)) {
                    unlink($dbPath);
                }
                return Command::FAILURE;
            }
        }

        $db->close();
        $io->success('All data exported to SQLite database');

        // If local-only mode, finish here
        if ($localOnly) {
            $io->success("Database saved to: {$dbPath}");
            $io->note('Skipping Zenodo upload (local-only mode)');
            return Command::SUCCESS;
        }

        // Step 3: Create or get Zenodo deposition
        $io->section('Step 3: Preparing Zenodo deposition');

        $depositionId = $input->getOption('deposition-id');
        $isNewVersion = false;

        try {
            if ($depositionId) {
                $io->text("Checking existing deposition ID: {$depositionId}");
                $deposition = $this->getDeposition($token, $depositionId);

                // Check if deposition is published - if so, create new version
                if (isset($deposition['submitted']) && $deposition['submitted'] === true) {
                    $io->text("Deposition is published. Creating new version...");
                    $newVersion = $this->createNewVersion($token, $depositionId);
                    $depositionId = $newVersion['id'];
                    $isNewVersion = true;
                    $io->success("Created new version with ID: {$depositionId}");
                } else {
                    $io->text("Using draft deposition ID: {$depositionId}");
                }
            } else {
                $deposition = $this->createDeposition(
                    $token,
                    $input->getOption('title'),
                    $input->getOption('description')
                );
                $depositionId = $deposition['id'];
                $io->success("Created new deposition with ID: {$depositionId}");
            }
        } catch (\Exception $e) {
            $io->error("Failed to create/get deposition: " . $e->getMessage());

            // Check if this is a network/DNS issue
            if (strpos($e->getMessage(), 'Could not resolve host') !== false) {
                $io->note('Network connectivity issue detected. Possible causes:');
                $io->listing([
                    'Network access may be restricted in your environment',
                    'DNS resolution may not be available',
                    'Firewall may be blocking outbound connections',
                ]);
                $io->text('Try running this command from a server with internet access, or check your network settings.');
            }

            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            return Command::FAILURE;
        }

        // Step 4: Delete existing files if this is a new version
        if ($isNewVersion) {
            $io->section('Step 4: Removing old files from new version');
            try {
                $this->deleteExistingFiles($token, $depositionId);
                $io->success("Removed old files");
            } catch (\Exception $e) {
                $io->warning("Could not delete old files: " . $e->getMessage());
            }
        }

        // Step 5: Upload SQLite database
        $io->section($isNewVersion ? 'Step 5: Uploading SQLite database to Zenodo' : 'Step 4: Uploading SQLite database to Zenodo');

        try {
            $this->uploadFile($token, $depositionId, $dbFilename, $dbPath);
            $io->success("Uploaded {$dbFilename}");
        } catch (\Exception $e) {
            $io->error("Failed to upload {$dbFilename}: " . $e->getMessage());
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            return Command::FAILURE;
        }

        // Step 6: Update metadata if needed (especially for new versions)
        if ($isNewVersion) {
            $io->text('Updating metadata for new version...');
            try {
                $this->updateMetadata($token, $depositionId, $input->getOption('title'), $input->getOption('description'));
                $io->success('Metadata updated');
            } catch (\Exception $e) {
                $io->warning("Could not update metadata: " . $e->getMessage());
            }
        }

        // Step 7: Publish automatically
        $io->section($isNewVersion ? 'Step 7: Publishing new version' : 'Step 5: Publishing deposition');

        try {
            $published = $this->publishDeposition($token, $depositionId);
            $io->success('Deposition published successfully!');
            $io->text("Access: " . self::ACCESS_RIGHT);
            $io->text("DOI: " . $published['doi']);
            $io->text("URL: " . $published['links']['record_html']);

            if ($isNewVersion) {
                $io->note("This is version " . ($published['metadata']['version'] ?? 'N/A') . " of the dataset");
            }
        } catch (\Exception $e) {
            $io->error("Failed to publish: " . $e->getMessage());
            $io->note("Database is uploaded but not published. Deposition ID: {$depositionId}");
            $io->text("You can publish manually at: https://sandbox.zenodo.org/deposit/{$depositionId}");
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            return Command::FAILURE;
        }

        // Cleanup
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        $io->success('Command completed successfully!');
        return Command::SUCCESS;
    }

    private function exportCsv(string $path): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';

        $response = $this->di['HttpClient']->request('GET', $baseUrl . $path);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Failed to export CSV from {$path}");
        }

        $content = $response->getContent();

        // Ensure content is UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        }

        return $content;
    }

    private function createTableFromCsv(\SQLite3 $db, string $tableName, string $csvContent): void
    {
        // Remove BOM if present
        $csvContent = str_replace("\xEF\xBB\xBF", '', $csvContent);

        // Parse CSV using a more robust method
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csvContent);
        rewind($handle);

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            throw new \RuntimeException("CSV content is empty for {$tableName}");
        }

        // Get headers from first row
        $headers = $rows[0];
        if (empty($headers)) {
            throw new \RuntimeException("No headers found in CSV for {$tableName}");
        }

        // Sanitize column names - replace only spaces and quotes, keep underscores
        $sanitizedHeaders = array_map(function($header) {
            $header = trim($header);
            // Replace spaces with underscores, remove quotes
            $header = str_replace([' ', '"', "'"], ['_', '', ''], $header);
            // Remove any other problematic characters but keep letters, numbers, and underscores
            $header = preg_replace('/[^\w]/', '_', $header);
            // Remove multiple consecutive underscores
            $header = preg_replace('/_+/', '_', $header);
            // Remove leading/trailing underscores
            $header = trim($header, '_');
            return $header;
        }, $headers);

        // Create table
        $columns = array_map(function($col) {
            return "\"{$col}\" TEXT";
        }, $sanitizedHeaders);

        $createTableSql = "CREATE TABLE IF NOT EXISTS \"{$tableName}\" (" . implode(', ', $columns) . ")";
        $db->exec($createTableSql);

        // Prepare insert statement
        $placeholders = implode(',', array_fill(0, count($headers), '?'));
        $insertSql = "INSERT INTO \"{$tableName}\" VALUES ({$placeholders})";
        $stmt = $db->prepare($insertSql);

        $rowCount = 0;
        // Skip header row and process data rows
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                continue;
            }

            // Pad or trim row to match header count
            $row = array_pad($row, count($headers), '');
            $row = array_slice($row, 0, count($headers));

            // Ensure UTF-8 encoding for all values
            $row = array_map(function($value) {
                if (!is_string($value)) {
                    return (string)$value;
                }
                // Ensure proper UTF-8 encoding
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value));
                }
                return $value;
            }, $row);

            // Bind parameters
            foreach ($row as $index => $value) {
                $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
            }

            $stmt->execute();
            $stmt->reset();
            $rowCount++;
        }

        $stmt->close();
    }

    private function createDeposition(string $token, string $title, string $description): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . '/deposit/depositions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'metadata' => [
                    'title' => $title,
                    'upload_type' => 'dataset',
                    'description' => $description,
                    'access_right' => self::ACCESS_RIGHT,
                    'creators' => [
                        ['name' => 'Your Name'], // Configure as needed
                    ],
                ],
            ],
        ]);

        return $response->toArray();
    }

    private function getDeposition(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('GET', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $response->toArray();
    }

    private function uploadFile(string $token, string $depositionId, string $filename, string $filepath): void
    {
        $deposition = $this->getDeposition($token, $depositionId);
        $bucketUrl = $deposition['links']['bucket'];

        // Read file contents as string
        $fileContents = file_get_contents($filepath);

        if ($fileContents === false) {
            throw new \RuntimeException("Failed to read file {$filepath}");
        }

        $response = $this->di['HttpClient']->request('PUT', "{$bucketUrl}/{$filename}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $fileContents,
        ]);

        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
            throw new \RuntimeException("Failed to upload file {$filename}");
        }
    }

    private function publishDeposition(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/actions/publish", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $response->toArray();
    }

    private function createNewVersion(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/actions/newversion", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        $result = $response->toArray();

        // Get the draft URL from the response and extract the new deposition ID
        $latestDraftUrl = $result['links']['latest_draft'];

        // Fetch the new draft deposition
        $draftResponse = $this->di['HttpClient']->request('GET', $latestDraftUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $draftResponse->toArray();
    }

    private function updateMetadata(string $token, string $depositionId, string $title, string $description): array
    {
        // Get current metadata
        $deposition = $this->getDeposition($token, $depositionId);
        $metadata = $deposition['metadata'] ?? [];

        // Ensure all required fields are present
        $metadata['title'] = $title;
        $metadata['description'] = $description;
        $metadata['upload_type'] = $metadata['upload_type'] ?? 'dataset';
        $metadata['access_right'] = self::ACCESS_RIGHT;

        // Ensure creators are present
        if (!isset($metadata['creators']) || empty($metadata['creators'])) {
            $metadata['creators'] = [
                ['name' => 'Your Name'], // Configure as needed
            ];
        }

        // Ensure publication_date is present (required for new versions)
        if (!isset($metadata['publication_date'])) {
            $metadata['publication_date'] = date('Y-m-d');
        }

        // Remove any fields that might cause issues
        unset($metadata['doi']);
        unset($metadata['prereserve_doi']);

        $response = $this->di['HttpClient']->request('PUT', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'metadata' => $metadata,
            ],
        ]);

        return $response->toArray();
    }

    private function deleteExistingFiles(string $token, string $depositionId): void
    {
        $deposition = $this->getDeposition($token, $depositionId);

        if (isset($deposition['files']) && is_array($deposition['files'])) {
            foreach ($deposition['files'] as $file) {
                $fileId = $file['id'];
                $this->di['HttpClient']->request('DELETE', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/files/{$fileId}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);
            }
        }
    }
}