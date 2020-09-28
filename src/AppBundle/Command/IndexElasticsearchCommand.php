<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\ObjectStorage\ArticleManager;
use AppBundle\ObjectStorage\BibVariaManager;
use AppBundle\ObjectStorage\BlogManager;
use AppBundle\ObjectStorage\BlogPostManager;
use AppBundle\ObjectStorage\BookChapterManager;
use AppBundle\ObjectStorage\BookClusterManager;
use AppBundle\ObjectStorage\BookManager;
use AppBundle\ObjectStorage\BookSeriesManager;
use AppBundle\ObjectStorage\JournalManager;
use AppBundle\ObjectStorage\ManuscriptManager;
use AppBundle\ObjectStorage\OccurrenceManager;
use AppBundle\ObjectStorage\OnlineSourceManager;
use AppBundle\ObjectStorage\PersonManager;
use AppBundle\ObjectStorage\PhdManager;
use AppBundle\ObjectStorage\TypeManager;
use AppBundle\ObjectStorage\VerseManager;
use AppBundle\Service\ElasticSearchService\ElasticBibliographyService;
use AppBundle\Service\ElasticSearchService\ElasticManuscriptService;
use AppBundle\Service\ElasticSearchService\ElasticOccurrenceService;
use AppBundle\Service\ElasticSearchService\ElasticPersonService;
use AppBundle\Service\ElasticSearchService\ElasticTypeService;
use AppBundle\Service\ElasticSearchService\ElasticVerseService;

class IndexElasticsearchCommand extends Command
{
    protected $di = [];

    /**
     * IndexElasticsearchCommand constructor.
     * @param ManuscriptManager $manuscriptManager
     * @param ElasticManuscriptService $elasticManuscriptService
     * @param OccurrenceManager $occurrenceManager
     * @param ElasticOccurrenceService $elasticOccurrenceService
     * @param TypeManager $typeManager
     * @param ElasticTypeService $elasticTypeService
     * @param PersonManager $personManager
     * @param ElasticPersonService $elasticPersonService
     * @param ElasticBibliographyService $elasticBibliographyService
     * @param ArticleManager $articleManager
     * @param BookManager $bookManager
     * @param BookChapterManager $bookChapterManager
     * @param OnlineSourceManager $onlineSourceManager
     * @param BookClusterManager $bookClusterManager
     * @param BookSeriesManager $bookSeriesManager
     * @param BlogManager $blogManager
     * @param BlogPostManager $blogPostManager
     * @param PhdManager $phdManager
     * @param BibVariaManager $bibVariaManager
     * @param JournalManager $journalManager
     * @param VerseManager $verseManager
     * @param ElasticVerseService $elasticVerseService
     */
    public function __construct(
        ManuscriptManager $manuscriptManager,
        ElasticManuscriptService $elasticManuscriptService,
        OccurrenceManager $occurrenceManager,
        ElasticOccurrenceService $elasticOccurrenceService,
        TypeManager $typeManager,
        ElasticTypeService $elasticTypeService,
        PersonManager $personManager,
        ElasticPersonService $elasticPersonService,
        ElasticBibliographyService $elasticBibliographyService,
        ArticleManager $articleManager,
        BookManager $bookManager,
        BookChapterManager $bookChapterManager,
        OnlineSourceManager $onlineSourceManager,
        BookClusterManager $bookClusterManager,
        BookSeriesManager $bookSeriesManager,
        BlogManager $blogManager,
        BlogPostManager $blogPostManager,
        PhdManager $phdManager,
        BibVariaManager $bibVariaManager,
        JournalManager $journalManager,
        VerseManager $verseManager,
        ElasticVerseService $elasticVerseService
    ) {
        $this->di = [
            'ManuscriptManager' => $manuscriptManager,
            'ElasticManuscriptService' => $elasticManuscriptService,
            'OccurrenceManager' => $occurrenceManager,
            'ElasticOccurrenceService' => $elasticOccurrenceService,
            'TypeManager' => $typeManager,
            'ElasticTypeService' => $elasticTypeService,
            'PersonManager' => $personManager,
            'ElasticPersonService' => $elasticPersonService,
            'ElasticBibliographyService' => $elasticBibliographyService,
            'ArticleManager' => $articleManager,
            'BookManager' => $bookManager,
            'BookChapterManager' => $bookChapterManager,
            'OnlineSourceManager' => $onlineSourceManager,
            'BookClusterManager' => $bookClusterManager,
            'BookSeriesManager' => $bookSeriesManager,
            'BlogManager' => $blogManager,
            'BlogPostManager' => $blogPostManager,
            'PhdManager' => $phdManager,
            'BibVariaManager' => $bibVariaManager,
            'JournalManager' => $journalManager,
            'VerseManager' => $verseManager,
            'ElasticVerseService' => $elasticVerseService,
        ];

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:elasticsearch:index')
            ->setDescription('Drops the old elasticsearch index and recreates it.')
            ->setHelp('This command allows you to reindex elasticsearch.')
            ->addOption('index', 'i', InputOption::VALUE_OPTIONAL, 'Which index should be reindexed?');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $index = $input->getOption('index');

        // Index all types
        // (Re)index manuscripts
        if ($index == null || $index == 'manuscript') {
            $manuscripts = $this->di['ManuscriptManager']->getAllShort();

            $this->di['ElasticManuscriptService']->setup();
            $this->di['ElasticManuscriptService']->addMultiple($manuscripts);
        }

        // (Re)index occurrences
        if ($index == null || $index == 'occurrence') {
            $occurrences = $this->di['OccurrenceManager']->getAllShort();

            $this->di['ElasticOccurrenceService']->setup();
            $this->di['ElasticOccurrenceService']->addMultiple($occurrences);
        }

        // (Re)index types
        if ($index == null || $index == 'type') {
            $occurrences = $this->di['TypeManager']->getAllShort();

            $this->di['ElasticTypeService']->setup();
            $this->di['ElasticTypeService']->addMultiple($occurrences);
        }

        // (Re)index persons
        if ($index == null || $index == 'person') {
            $persons = $this->di['PersonManager']->getAllShort();

            $this->di['ElasticPersonService']->setup();
            $this->di['ElasticPersonService']->addMultiple($persons);
        }

        // (Re)index bibliography items
        if ($index == null || $index == 'bibliography') {
            $this->di['ElasticBibliographyService']->setup();

            $bibTypes = [
                'Article',
                'Book',
                'BookChapter',
                'OnlineSource',
                'BookCluster',
                'BookSeries',
                'Blog',
                'BlogPost',
                'Phd',
                'BibVaria',
            ];

            foreach ($bibTypes as $bibType) {
                $items = $this->di[$bibType . 'Manager']->getAllShort();
                $this->di['ElasticBibliographyService']->addMultiple($items);
            }

            $items = $this->di['JournalManager']->getAll();
            $this->di['ElasticBibliographyService']->addMultiple($items);
        }

        // (Re)index verses
        if ($index == null || $index == 'verse') {
            $verses = $this->di['VerseManager']->getAllShort();

            $this->di['ElasticVerseService']->setupVerses();
            $this->di['ElasticVerseService']->addMultiple($verses);
        }
    }
}
