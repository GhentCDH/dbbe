<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $manuscriptManager = $this->getContainer()->get('manuscript_manager');
        $occurrenceManager = $this->getContainer()->get('occurrence_manager');
        $personManager = $this->getContainer()->get('person_manager');

        $elasticSearchService = $this->getContainer()->get('elasticsearch_service');
        $manuscriptElasticService = $this->getContainer()->get('manuscript_elastic_service');
        $occurrenceElasticService = $this->getContainer()->get('occurrence_elastic_service');
        $personElasticService = $this->getContainer()->get('person_elastic_service');

        // Index all types
        // (Re)index manuscripts
        $manuscriptElasticService->setupManuscripts();
        $manuscripts = $manuscriptManager->getAllManuscripts();
        $manuscriptElasticService->addManuscripts($manuscripts);

        // (Re)index occurrences
        $occurrenceElasticService->setupOccurrences();
        $occurrences = $occurrenceManager->getAllOccurrences();
        $occurrenceElasticService->addOccurrences($occurrences);

        // (Re)index persons
        $personElasticService->setupPersons();
        $persons = $personManager->getAllPersons();
        $personElasticService->addPersons($persons);
    }
}
