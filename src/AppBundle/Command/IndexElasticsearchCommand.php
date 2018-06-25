<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexElasticsearchCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:elasticsearch:index')
            ->setDescription('Drops the old elasticsearch index and recreates it.')
            ->setHelp('This command allows you to reindex elasticsearch.')
            ->addOption('index', 'i', InputOption::VALUE_OPTIONAL, 'Which index should be reindexed?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $index = $input->getOption('index');

        // Index all types
        // (Re)index manuscripts
        if ($index == null || $index == 'manuscript') {
            $manuscriptManager = $this->getContainer()->get('manuscript_manager');
            $manuscriptElasticService = $this->getContainer()->get('manuscript_elastic_service');
            $manuscriptElasticService->setupManuscripts();
            $manuscripts = $manuscriptManager->getAllManuscripts();
            $manuscriptElasticService->addManuscripts($manuscripts);
        }

        // (Re)index occurrences
        if ($index == null || $index == 'occurrence') {
            $occurrenceManager = $this->getContainer()->get('occurrence_manager');
            $occurrenceElasticService = $this->getContainer()->get('occurrence_elastic_service');
            $occurrenceElasticService->setupOccurrences();
            $occurrences = $occurrenceManager->getAllOccurrences();
            $occurrenceElasticService->addOccurrences($occurrences);
        }

        // (Re)index persons
        if ($index == null || $index == 'person') {
            $personManager = $this->getContainer()->get('person_manager');
            $personElasticService = $this->getContainer()->get('person_elastic_service');
            $personElasticService->setupPersons();
            $persons = $personManager->getAllPersons();
            $personElasticService->addPersons($persons);
        }
    }
}
