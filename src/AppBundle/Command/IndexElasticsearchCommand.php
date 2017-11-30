<?php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Elastica\Document;
use Elastica\Mapping;

class IndexElasticsearchCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:elasticsearch:index')
            ->setDescription('Drops the old elasticsearch index and recreates it.')
            ->setHelp('This command allows you to reindex elasticsearch.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get database and elasticsearch clients
        $conn = $this
            ->getContainer()
            ->get('doctrine')
            ->getManager()
            ->getConnection();

        $index = $this
            ->getContainer()
            ->get('elasticsearch_service')
            ->getIndex();

        // Delete index
        if ($index->exists()) {
            $index->delete();
        }

        // Index all types
        $this->indexManuscript($conn, $index);
    }

    private function indexManuscript(\Doctrine\DBAL\Connection $conn, \Elastica\Index $index)
    {
        // Get manuscripts from database
        $statement = $conn->prepare('SELECT * FROM data.manuscript_get_all');
        $statement->execute();
        $manuscripts = $statement->fetchAll();

        // Add to elasticsearch
        $type = $index->getType('manuscript');

        $documents = [];
        foreach ($manuscripts as $manuscript) {
            // Extract date_floor and date_ceiling
            preg_match(
                '/[(](\d{4}[-]\d{2}-\d{2})[,](\d{4}[-]\d{2}-\d{2})[)]/',
                $manuscript['completion_date'],
                $fuzzy_date
            );
            if (count($fuzzy_date) == 0) {
                $fuzzy_date = [null, null, null];
            }

            $ms = [
                'id' => $manuscript['identity'],
                'title' => $manuscript['title'],
                'date_floor' => $fuzzy_date[1],
                'date_ceiling' => $fuzzy_date[2],
                'parent_genre' => $manuscript['parent_genre'],
                'child_genre' => $manuscript['child_genre'],
            ];

            $documents[] = new Document($manuscript['identity'], $ms);

            // Bulk index each 500 documents
            if (count($documents == 500)) {
                $type->addDocuments($documents);
                $documents = [];
            }
        }

        // Bulk index the rest of the documents
        if (count($documents) > 0) {
            $type->addDocuments($documents);
        }
        $type->getIndex()->refresh();
    }
}
