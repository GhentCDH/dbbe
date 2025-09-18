<?php

namespace App\Command;

use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticVerseService;
use App\ElasticSearchService\ElasticTypeService;
use App\ElasticSearchService\ElasticPersonService;
use App\ElasticSearchService\ElasticManuscriptService;
use App\ElasticSearchService\ElasticBibliographyService;
use App\ObjectStorage\OccurrenceManager;
use App\ObjectStorage\TypeManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\ManuscriptManager;
use App\ObjectStorage\BibliographyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportDataCommand extends Command
{
    protected static $defaultName = 'app:export-data';

    public function __construct(
        private OccurrenceManager $occurrenceManager,
        private ElasticOccurrenceService $occurrenceService,
        private ElasticVerseService $verseService,
        private TypeManager $typeManager,
        private ElasticTypeService $typeService,
        private PersonManager $personManager,
        private ElasticPersonService $personService,
        private ManuscriptManager $manuscriptManager,
        private ElasticManuscriptService $manuscriptService,
        private BibliographyManager $bibliographyManager,
        private ElasticBibliographyService $bibliographyService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Export all data to JSON files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be exported without actually doing it')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Output directory', './data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting JSON data export...');

        $isDryRun = $input->getOption('dry-run');
        $outputDir = './data-export';

        if ($isDryRun) {
            $output->writeln('<info>DRY RUN MODE - No files will be written</info>');
        }

        $entities = [
            'occurrences' => [
                'manager' => $this->occurrenceManager,
                'services' => [$this->occurrenceService, $this->verseService]
            ],
            'types' => [
                'manager' => $this->typeManager,
                'services' => [$this->typeService]
            ],
            'persons' => [
                'manager' => $this->personManager,
                'services' => [$this->personService]
            ],
            'manuscripts' => [
                'manager' => $this->manuscriptManager,
                'services' => [$this->manuscriptService]
            ],
            'bibliographies' => [
                'manager' => $this->bibliographyManager,
                'services' => [$this->bibliographyService]
            ],
        ];

        foreach ($entities as $entity => $config) {
            $output->writeln("Processing {$entity}...");

            $manager = $config['manager'];
            $services = $config['services'];

            // Create entity directory
            $entityDir = "{$outputDir}/{$entity}";
            if (!$isDryRun && !is_dir($entityDir)) {
                mkdir($entityDir, 0755, true);
                $output->writeln("  Created directory: {$entityDir}");
            }

            try {
                if ($isDryRun) {
                    $result = $services[0]->runFullSearch(['limit' => 0], true);
                    $output->writeln("  Would export {$result['count']} {$entity} records");
                } else {
                    $output->writeln("  Exporting {$entity} JSON...");
                    $jsonStream = $this->callManagerMethod($manager, 'generateJsonStream', $services);
                    $jsonContent = stream_get_contents($jsonStream);
                    file_put_contents("{$entityDir}/{$entity}.json", $jsonContent);
                    fclose($jsonStream);

                    $recordCount = substr_count($jsonContent, '"id"');
                    $output->writeln("  <info>Exported {$recordCount} {$entity} records</info>");
                }
            } catch (\Exception $e) {
                $output->writeln("  <error>Error exporting {$entity}: " . $e->getMessage() . "</error>");
                return Command::FAILURE;
            }
        }

        if (!$isDryRun) {
            $this->generateMetadata($outputDir, $output);
        }

        $output->writeln('<info>JSON data export completed successfully</info>');
        return Command::SUCCESS;
    }

    private function callManagerMethod($manager, string $method, array $services): mixed
    {
        return match (count($services)) {
            1 => $manager->$method([], $services[0], true),
            2 => $manager->$method([], $services[0], $services[1], true),
            default => throw new \InvalidArgumentException('Unsupported number of services')
        };
    }

    private function generateMetadata(string $outputDir, OutputInterface $output): void
    {
        $metadata = [
            'export_date' => date('c'),
            'export_timestamp' => time(),
            'exported_entities' => [],
        ];

        foreach (glob("{$outputDir}/*/") as $entityDir) {
            $entity = basename($entityDir);
            $jsonFile = "{$entityDir}/{$entity}.json";

            if (file_exists($jsonFile)) {
                $metadata['exported_entities'][$entity] = [
                    'file_size' => filesize($jsonFile),
                    'record_count' => substr_count(file_get_contents($jsonFile), '"id"'),
                ];
            }
        }

        file_put_contents("{$outputDir}/export_metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));
        $output->writeln("  Generated metadata file");
    }

    private function commitToGit(string $dataPath): void
    {
        $commands = [
            "git add {$dataPath}/",
            "git commit -m 'Data export " . date('Y-m-d H:i:s') . "'",
            "git push"
        ];

        foreach ($commands as $command) {
            $result = exec($command . ' 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \RuntimeException("Git command failed: {$command}\nOutput: " . implode("\n", $output));
            }
        }
    }
}