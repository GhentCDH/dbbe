<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexElasticsearchCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        //TODO: add argument (person / congres / organisation)
        $this
            ->setName('app:elasticsearch:index')
            ->setDescription('Drops the old elasticsearch index and recreates it.')
            ->setHelp('This command allows you to reindex elasticsearch.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get database and elasticsearch clients
        $manuscriptManager = $this->getContainer()->get('manuscript_manager');

        $manuscriptElasticService = $this->getContainer()->get('manuscript_elastic_service');

        // Index all types
        // (Re)index manuscripts
        $elasticSearchService->resetIndex('documents');
        $manuscripts = $manuscriptManager->getAllManuscripts();
        $manuscriptElasticService->setupManuscripts();
        $manuscriptElasticService->addManuscripts($manuscripts);
    }
}
