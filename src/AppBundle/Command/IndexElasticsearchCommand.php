<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\Service\DatabaseService;
use AppBundle\Service\ElasticsearchService;

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
        $msdb = $this
            ->getContainer()
            ->get('database_manuscript_service');

        $es = $this
            ->getContainer()
            ->get('elasticsearch_service');

        // Index all types
        // (Re)index manuscripts
        $es->resetIndex('documents');
        $mcs = $msdb->getCompleteManuscripts();
        $es->addManuscripts($mcs);
    }
}
