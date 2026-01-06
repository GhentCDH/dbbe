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
            $db->exec('PRAGMA encoding = "UTF-8"');
            $db->exec('PRAGMA foreign_keys = ON');
            $io->success("Created SQLite database: {$dbFilename}");
        } catch (\Exception $e) {
            $io->error("Failed to create SQLite database: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 2: Create normalized schema
        $io->section('Step 2: Creating database schema');
        try {
            $this->createNormalizedSchema($db);
            $io->success('Created normalized schema with subjects lookup table');
        } catch (\Exception $e) {
            $io->error("Failed to create schema: " . $e->getMessage());
            $db->close();
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            return Command::FAILURE;
        }

        // Step 3: Export data from endpoints and populate tables
        $io->section('Step 3: Exporting data from endpoints');

        // Import in specific order due to foreign key constraints
        $importOrder = ['manuscripts', 'occurrences', 'persons', 'types'];

        foreach ($importOrder as $tableName) {
            $io->text("Exporting {$tableName}...");

            try {
                $path = self::ENDPOINTS[$tableName];
                $csvContent = $this->exportCsv($path);
                $this->importCsvData($db, $tableName, $csvContent, $io);
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

        // Steps 4-8: Zenodo upload (same as before)
        $io->section('Step 4: Preparing Zenodo deposition');

        $depositionId = $input->getOption('deposition-id');
        $isNewVersion = false;

        $maxRetries = 3;
        $retryDelay = 2;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($depositionId) {
                    $io->text("Checking existing deposition ID: {$depositionId}");
                    $deposition = $this->getDeposition($token, $depositionId);

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

                break;

            } catch (\Exception $e) {
                $isNetworkError = strpos($e->getMessage(), 'Could not resolve host') !== false;

                if ($isNetworkError && $attempt < $maxRetries) {
                    $io->warning("Network error (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                    $io->text("Retrying in {$retryDelay} seconds...");
                    sleep($retryDelay);
                    continue;
                }

                $io->error("Failed to create/get deposition: " . $e->getMessage());

                if ($isNetworkError) {
                    $io->note('Network connectivity issue detected. Possible solutions:');
                    $io->listing([
                        'Wait a few moments and try again',
                        'Clear DNS cache: sudo dscacheutil -flushcache (macOS) or sudo systemd-resolve --flush-caches (Linux)',
                        'Try: ping sandbox.zenodo.org to verify DNS resolution',
                        'Check if you recently switched networks',
                    ]);
                }

                if (file_exists($dbPath)) {
                    unlink($dbPath);
                }
                return Command::FAILURE;
            }
        }

        if ($isNewVersion) {
            $io->section('Step 5: Removing old files from new version');
            try {
                $this->deleteExistingFiles($token, $depositionId);
                $io->success("Removed old files");
            } catch (\Exception $e) {
                $io->warning("Could not delete old files: " . $e->getMessage());
            }
        }

        $io->section($isNewVersion ? 'Step 6: Uploading SQLite database to Zenodo' : 'Step 5: Uploading SQLite database to Zenodo');

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

        if ($isNewVersion) {
            $io->text('Updating metadata for new version...');
            try {
                $this->updateMetadata($token, $depositionId, $input->getOption('title'), $input->getOption('description'));
                $io->success('Metadata updated');
            } catch (\Exception $e) {
                $io->warning("Could not update metadata: " . $e->getMessage());
            }
        }

        $io->section($isNewVersion ? 'Step 7: Publishing new version' : 'Step 6: Publishing deposition');

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

        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        $io->success('Command completed successfully!');
        return Command::SUCCESS;
    }

    private function createNormalizedSchema(\SQLite3 $db): void
    {
        // Lookup tables for genres, metres, and subjects
        $db->exec('CREATE TABLE genres (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        )');

        $db->exec('CREATE TABLE metres (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        )');

        $db->exec('CREATE TABLE subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        )');

        // Main tables
        $db->exec('CREATE TABLE manuscripts (
            id TEXT PRIMARY KEY,
            name TEXT,
            diktyon TEXT,
            city TEXT,
            library TEXT,
            content TEXT,
            person_content TEXT,
            origin TEXT
        )');

        $db->exec('CREATE TABLE occurrences (
            id TEXT PRIMARY KEY,
            incipit TEXT,
            verses TEXT,
            date_floor_year TEXT,
            date_ceiling_year TEXT,
            manuscript_id TEXT,
            manuscript_name TEXT
        )');

        $db->exec('CREATE INDEX idx_occurrences_manuscript_id ON occurrences(manuscript_id)');

        $db->exec('CREATE TABLE persons (
            id TEXT PRIMARY KEY,
            name TEXT,
            born_floor TEXT,
            born_ceiling TEXT,
            death_floor TEXT,
            death_ceiling TEXT,
            roles TEXT
        )');

        $db->exec('CREATE TABLE types (
            id TEXT PRIMARY KEY,
            text_original TEXT
        )');

        // Junction tables for many-to-many relationships
        $db->exec('CREATE TABLE occurrence_genres (
            occurrence_id TEXT NOT NULL,
            genre_id INTEGER NOT NULL,
            PRIMARY KEY (occurrence_id, genre_id),
            FOREIGN KEY (occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY (genre_id) REFERENCES genres(id)
        )');

        $db->exec('CREATE TABLE occurrence_metres (
            occurrence_id TEXT NOT NULL,
            metre_id INTEGER NOT NULL,
            PRIMARY KEY (occurrence_id, metre_id),
            FOREIGN KEY (occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY (metre_id) REFERENCES metres(id)
        )');

        $db->exec('CREATE TABLE occurrence_subjects (
            occurrence_id TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            PRIMARY KEY (occurrence_id, subject_id),
            FOREIGN KEY (occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id)
        )');

        $db->exec('CREATE TABLE type_genres (
            type_id TEXT NOT NULL,
            genre_id INTEGER NOT NULL,
            PRIMARY KEY (type_id, genre_id),
            FOREIGN KEY (type_id) REFERENCES types(id),
            FOREIGN KEY (genre_id) REFERENCES genres(id)
        )');

        $db->exec('CREATE TABLE type_metres (
            type_id TEXT NOT NULL,
            metre_id INTEGER NOT NULL,
            PRIMARY KEY (type_id, metre_id),
            FOREIGN KEY (type_id) REFERENCES types(id),
            FOREIGN KEY (metre_id) REFERENCES metres(id)
        )');

        $db->exec('CREATE TABLE type_subjects (
            type_id TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            PRIMARY KEY (type_id, subject_id),
            FOREIGN KEY (type_id) REFERENCES types(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id)
        )');

        $db->exec('CREATE TABLE type_occurrences (
            type_id TEXT NOT NULL,
            occurrence_id TEXT NOT NULL,
            PRIMARY KEY (type_id, occurrence_id)
        )');

        $db->exec('CREATE INDEX idx_type_occurrences_type ON type_occurrences(type_id)');
        $db->exec('CREATE INDEX idx_type_occurrences_occurrence ON type_occurrences(occurrence_id)');
    }

    private function importCsvData(\SQLite3 $db, string $tableName, string $csvContent, SymfonyStyle $io): void
    {
        $csvContent = str_replace("\xEF\xBB\xBF", '', $csvContent);

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

        $headers = $rows[0];

        switch ($tableName) {
            case 'manuscripts':
                $this->importManuscripts($db, $rows, $headers);
                break;
            case 'occurrences':
                $this->importOccurrences($db, $rows, $headers);
                break;
            case 'persons':
                $this->importPersons($db, $rows, $headers);
                break;
            case 'types':
                $this->importTypes($db, $rows, $headers);
                break;
        }
    }

    private function importManuscripts(\SQLite3 $db, array $rows, array $headers): void
    {
        $stmt = $db->prepare('INSERT OR IGNORE INTO manuscripts VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) continue;

            $row = array_pad($row, count($headers), '');
            $row = array_slice($row, 0, count($headers));

            for ($j = 0; $j < 8; $j++) {
                $stmt->bindValue($j + 1, $row[$j] ?? '', SQLITE3_TEXT);
            }
            $stmt->execute();
            $stmt->reset();
        }
        $stmt->close();
    }

    private function importOccurrences(\SQLite3 $db, array $rows, array $headers): void
    {
        $stmt = $db->prepare('INSERT OR IGNORE INTO occurrences VALUES (?, ?, ?, ?, ?, ?, ?)');

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) continue;

            $row = array_pad($row, count($headers), '');

            $id = $row[0] ?? '';
            $incipit = $row[1] ?? '';
            $verses = $row[2] ?? '';
            $genres = $row[3] ?? '';
            $subjects = $row[4] ?? '';
            $metres = $row[5] ?? '';
            $dateFloor = $row[6] ?? '';
            $dateCeiling = $row[7] ?? '';
            $manuscriptId = $row[8] ?? '';
            $manuscriptName = $row[9] ?? '';

            // Insert main occurrence record (without subjects)
            $stmt->bindValue(1, $id, SQLITE3_TEXT);
            $stmt->bindValue(2, $incipit, SQLITE3_TEXT);
            $stmt->bindValue(3, $verses, SQLITE3_TEXT);
            $stmt->bindValue(4, $dateFloor, SQLITE3_TEXT);
            $stmt->bindValue(5, $dateCeiling, SQLITE3_TEXT);
            $stmt->bindValue(6, $manuscriptId, SQLITE3_TEXT);
            $stmt->bindValue(7, $manuscriptName, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();

            // Handle genres
            if (!empty($genres)) {
                $this->linkMultipleValues($db, 'genres', 'occurrence_genres', $id, 'occurrence_id', $genres);
            }

            // Handle metres
            if (!empty($metres)) {
                $this->linkMultipleValues($db, 'metres', 'occurrence_metres', $id, 'occurrence_id', $metres);
            }

            // Handle subjects
            if (!empty($subjects)) {
                $this->linkMultipleValues($db, 'subjects', 'occurrence_subjects', $id, 'occurrence_id', $subjects);
            }
        }
        $stmt->close();
    }

    private function importPersons(\SQLite3 $db, array $rows, array $headers): void
    {
        $stmt = $db->prepare('INSERT OR IGNORE INTO persons VALUES (?, ?, ?, ?, ?, ?, ?)');

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) continue;

            $row = array_pad($row, 7, ''); // pad to 7 columns

            $stmt->bindValue(1, $row[0] ?? '', SQLITE3_TEXT); // id
            $stmt->bindValue(2, $row[1] ?? '', SQLITE3_TEXT); // name
            $stmt->bindValue(3, $row[2] ?? '', SQLITE3_TEXT); // born_floor
            $stmt->bindValue(4, $row[3] ?? '', SQLITE3_TEXT); // born_ceiling
            $stmt->bindValue(5, $row[4] ?? '', SQLITE3_TEXT); // death_floor
            $stmt->bindValue(6, $row[5] ?? '', SQLITE3_TEXT); // death_ceiling
            $stmt->bindValue(7, $row[6] ?? '', SQLITE3_TEXT); // roles (pipe-separated)
            $stmt->execute();
            $stmt->reset();
        }
        $stmt->close();
    }

    private function importTypes(\SQLite3 $db, array $rows, array $headers): void
    {
        $stmt = $db->prepare('INSERT OR IGNORE INTO types VALUES (?, ?)');

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) continue;

            $row = array_pad($row, count($headers), '');

            $id = $row[0] ?? '';
            $genres = $row[1] ?? '';
            $subjects = $row[2] ?? '';
            $metres = $row[3] ?? '';
            $textOriginal = $row[4] ?? '';
            $occurrenceIds = $row[5] ?? '';

            // Insert main type record (without subjects)
            $stmt->bindValue(1, $id, SQLITE3_TEXT);
            $stmt->bindValue(2, $textOriginal, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();

            // Handle genres
            if (!empty($genres)) {
                $this->linkMultipleValues($db, 'genres', 'type_genres', $id, 'type_id', $genres);
            }

            // Handle metres
            if (!empty($metres)) {
                $this->linkMultipleValues($db, 'metres', 'type_metres', $id, 'type_id', $metres);
            }

            // Handle subjects
            if (!empty($subjects)) {
                $this->linkMultipleValues($db, 'subjects', 'type_subjects', $id, 'type_id', $subjects);
            }

            // Handle occurrence relationships
            if (!empty($occurrenceIds)) {
                $occIds = array_filter(array_map('trim', explode('|', $occurrenceIds)));
                foreach ($occIds as $occId) {
                    $linkStmt = $db->prepare('INSERT OR IGNORE INTO type_occurrences VALUES (?, ?)');
                    $linkStmt->bindValue(1, $id, SQLITE3_TEXT);
                    $linkStmt->bindValue(2, $occId, SQLITE3_TEXT);
                    $linkStmt->execute();
                    $linkStmt->close();
                }
            }
        }
        $stmt->close();
    }

    private function linkMultipleValues(\SQLite3 $db, string $lookupTable, string $junctionTable, string $entityId, string $entityColumn, string $pipeSeparatedValues): void
    {
        $values = array_filter(array_map('trim', explode('|', $pipeSeparatedValues)));

        foreach ($values as $value) {
            if (empty($value)) continue;

            // Insert into lookup table if not exists
            $insertStmt = $db->prepare("INSERT OR IGNORE INTO {$lookupTable} (name) VALUES (?)");
            $insertStmt->bindValue(1, $value, SQLITE3_TEXT);
            $insertStmt->execute();
            $insertStmt->close();

            // Get the ID
            $selectStmt = $db->prepare("SELECT id FROM {$lookupTable} WHERE name = ?");
            $selectStmt->bindValue(1, $value, SQLITE3_TEXT);
            $result = $selectStmt->execute();
            $lookupId = $result->fetchArray(SQLITE3_ASSOC)['id'];
            $selectStmt->close();

            // Insert into junction table
            $junctionStmt = $db->prepare("INSERT OR IGNORE INTO {$junctionTable} VALUES (?, ?)");
            $junctionStmt->bindValue(1, $entityId, SQLITE3_TEXT);
            $junctionStmt->bindValue(2, $lookupId, SQLITE3_NUM);
            $junctionStmt->execute();
            $junctionStmt->close();
        }
    }

    private function exportCsv(string $path): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $response = $this->di['HttpClient']->request('GET', $baseUrl . $path);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Failed to export CSV from {$path}");
        }

        $content = $response->getContent();

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        }

        return $content;
    }

    private function createDeposition(string $token, string $title, string $description): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . '/deposit/depositions', [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'json' => [
                'metadata' => [
                    'title' => $title,
                    'upload_type' => 'dataset',
                    'description' => $description,
                    'access_right' => self::ACCESS_RIGHT,
                    'creators' => [['name' => 'Your Name']],
                ],
            ],
        ]);
        return $response->toArray();
    }

    private function getDeposition(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('GET', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        return $response->toArray();
    }

    private function uploadFile(string $token, string $depositionId, string $filename, string $filepath): void
    {
        $deposition = $this->getDeposition($token, $depositionId);
        $bucketUrl = $deposition['links']['bucket'];
        $fileContents = file_get_contents($filepath);

        if ($fileContents === false) {
            throw new \RuntimeException("Failed to read file {$filepath}");
        }

        $response = $this->di['HttpClient']->request('PUT', "{$bucketUrl}/{$filename}", [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/octet-stream'],
            'body' => $fileContents,
        ]);

        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
            throw new \RuntimeException("Failed to upload file {$filename}");
        }
    }

    private function publishDeposition(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/actions/publish", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        return $response->toArray();
    }

    private function createNewVersion(string $token, string $depositionId): array
    {
        $response = $this->di['HttpClient']->request('POST', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/actions/newversion", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        $result = $response->toArray();
        $latestDraftUrl = $result['links']['latest_draft'];
        $draftResponse = $this->di['HttpClient']->request('GET', $latestDraftUrl, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        return $draftResponse->toArray();
    }

    private function updateMetadata(string $token, string $depositionId, string $title, string $description): array
    {
        $deposition = $this->getDeposition($token, $depositionId);
        $metadata = $deposition['metadata'] ?? [];
        $metadata['title'] = $title;
        $metadata['description'] = $description;
        $metadata['upload_type'] = $metadata['upload_type'] ?? 'dataset';
        $metadata['access_right'] = self::ACCESS_RIGHT;

        if (!isset($metadata['creators']) || empty($metadata['creators'])) {
            $metadata['creators'] = [['name' => 'Your Name']];
        }

        if (!isset($metadata['publication_date'])) {
            $metadata['publication_date'] = date('Y-m-d');
        }

        unset($metadata['doi']);
        unset($metadata['prereserve_doi']);

        $response = $this->di['HttpClient']->request('PUT', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}", [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'json' => ['metadata' => $metadata],
        ]);
        return $response->toArray();
    }

    private function deleteExistingFiles(string $token, string $depositionId): void
    {
        $deposition = $this->getDeposition($token, $depositionId);
        if (isset($deposition['files']) && is_array($deposition['files'])) {
            foreach ($deposition['files'] as $file) {
                $fileId = $file['id'];
                $this->di['HttpClient']->request('DELETE', self::ZENODO_SANDBOX_API . "/deposit/depositions/{$depositionId}/files/{fileId}", [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                ]);
            }
        }
    }
}


