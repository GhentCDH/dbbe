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
            $manuscripts = $this->getContainer()->get('manuscript_manager')->getAllShort();

            $manuscriptElasticService = $this->getContainer()->get('manuscript_elastic_service');
            $manuscriptElasticService->setup();
            $manuscriptElasticService->addMultiple($manuscripts);
        }

        // (Re)index occurrences
        if ($index == null || $index == 'occurrence') {
            $occurrences = $this->getContainer()->get('occurrence_manager')->getAllShort();

            $occurrenceElasticService = $this->getContainer()->get('occurrence_elastic_service');
            $occurrenceElasticService->setup();
            $occurrenceElasticService->addMultiple($occurrences);
        }

        // (Re)index types
        if ($index == null || $index == 'type') {
            $occurrences = $this->getContainer()->get('type_manager')->getAllShort();

            $occurrenceElasticService = $this->getContainer()->get('type_elastic_service');
            $occurrenceElasticService->setup();
            $occurrenceElasticService->addMultiple($occurrences);
        }

        // (Re)index persons
        if ($index == null || $index == 'person') {
            $persons = $this->getContainer()->get('person_manager')->getAllShort();

            $personElasticService = $this->getContainer()->get('person_elastic_service');
            $personElasticService->setup();
            $personElasticService->addMultiple($persons);
        }

        // (Re)index bibliography items
        if ($index == null || $index == 'bibliography') {
            $bibliographyElasticService = $this->getContainer()->get('bibliography_elastic_service');
            $bibliographyElasticService->setup();

            foreach (['article', 'book', 'book_chapter', 'online_source', 'book_cluster', 'book_series'] as $type) {
                $items = $this->getContainer()->get($type . '_manager')->getAllShort();
                $bibliographyElasticService->addMultiple($items);
            }

            $items = $this->getContainer()->get('journal_manager')->getAll();
            $bibliographyElasticService->addMultiple($items);
        }

        // (Re)index verses
        if ($index == null || $index == 'verse') {
            $verses = $this->getContainer()->get('verse_manager')->getAllShort();

            $verseElasticService = $this->getContainer()->get('verse_elastic_service');
            $verseElasticService->setupVerses();
            $verseElasticService->addMultiple($verses);
        }
    }
}
