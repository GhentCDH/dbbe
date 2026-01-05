<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExportToZenodoCommand extends Command
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
     * ExportToZenodoCommand constructor.
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
            ->setName('app:export-to-zenodo')
            ->setDescription('Export CSVs from all endpoints and publish to Zenodo sandbox')
            ->setHelp('This command exports CSVs from manuscripts, occurrences, persons, and types endpoints and publishes them to Zenodo sandbox.')
            ->addOption('zenodo-token', 't', InputOption::VALUE_OPTIONAL, 'Zenodo sandbox access token')
            ->addOption('deposition-id', 'd', InputOption::VALUE_OPTIONAL, 'Existing Zenodo deposition ID (if updating)')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Deposition title', 'CSV Export Data')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Deposition description', 'Exported CSV files from application');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get Zenodo token
        $token = $input->getOption('zenodo-token') ?: $this->di['ZenodoAccessToken'];
        if (empty($token)) {
            $io->error('Zenodo access token is required. Provide via --zenodo-token option or configure in services.');
            return Command::FAILURE;
        }

        $io->title('Export CSVs and Publish to Zenodo Sandbox');

        $io->section('Step 1: Exporting CSVs from endpoints');
        $exportedFiles = [];

        foreach (self::ENDPOINTS as $name => $path) {
            $io->text("Exporting {$name}...");

            try {
                $csvContent = $this->exportCsv($path);
                $filename = $name . '_export_' . date('Y-m-d_His') . '.csv';
                $filepath = sys_get_temp_dir() . '/' . $filename;

                file_put_contents($filepath, $csvContent);
                $exportedFiles[$filename] = $filepath;

                $io->success("Exported {$name} to {$filename}");
            } catch (\Exception $e) {
                $io->error("Failed to export {$name}: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // Step 2: Create or get Zenodo deposition
        $io->section('Step 2: Preparing Zenodo deposition');

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
            $this->cleanupFiles($exportedFiles);
            return Command::FAILURE;
        }

        // Step 3: Delete existing files if this is a new version
        if ($isNewVersion) {
            $io->section('Step 3: Removing old files from new version');
            try {
                $this->deleteExistingFiles($token, $depositionId);
                $io->success("Removed old files");
            } catch (\Exception $e) {
                $io->warning("Could not delete old files: " . $e->getMessage());
            }
        }

        // Step 4: Upload files
        $io->section($isNewVersion ? 'Step 4: Uploading new files to Zenodo' : 'Step 3: Uploading files to Zenodo');

        foreach ($exportedFiles as $filename => $filepath) {
            $io->text("Uploading {$filename}...");

            try {
                $this->uploadFile($token, $depositionId, $filename, $filepath);
                $io->success("Uploaded {$filename}");
            } catch (\Exception $e) {
                $io->error("Failed to upload {$filename}: " . $e->getMessage());
                $this->cleanupFiles($exportedFiles);
                return Command::FAILURE;
            }
        }

        // Step 5: Update metadata if needed (especially for new versions)
        if ($isNewVersion) {
            $io->text('Updating metadata for new version...');
            try {
                $this->updateMetadata($token, $depositionId, $input->getOption('title'), $input->getOption('description'));
                $io->success('Metadata updated');
            } catch (\Exception $e) {
                $io->warning("Could not update metadata: " . $e->getMessage());
            }
        }

        // Step 6: Publish automatically
        $io->section($isNewVersion ? 'Step 6: Publishing new version' : 'Step 4: Publishing deposition');

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
            $io->note("Files are uploaded but not published. Deposition ID: {$depositionId}");
            $io->text("You can publish manually at: https://sandbox.zenodo.org/deposit/{$depositionId}");
            $this->cleanupFiles($exportedFiles);
            return Command::FAILURE;
        }

        // Cleanup
        $this->cleanupFiles($exportedFiles);

        $io->success('Command completed successfully!');
        return Command::SUCCESS;
    }

    private function exportCsv(string $path): string
    {
        // Option 1: If you need to make an HTTP request to your own application
        // Replace with your application's base URL
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';

        $response = $this->di['HttpClient']->request('GET', $baseUrl . $path);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Failed to export CSV from {$path}");
        }

        return $response->getContent();
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

    private function cleanupFiles(array $files): void
    {
        foreach ($files as $filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}