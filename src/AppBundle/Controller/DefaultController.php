<?php

namespace AppBundle\Controller;

use Doctrine\DBAL\DBALException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Service\DatabaseService\NewsEventService;
use AppBundle\ObjectStorage\OccurrenceManager;
use AppBundle\ObjectStorage\TypeManager;
use AppBundle\ObjectStorage\ManuscriptManager;
use AppBundle\ObjectStorage\PersonManager;
use AppBundle\ObjectStorage\ArticleManager;
use AppBundle\ObjectStorage\BookManager;
use AppBundle\ObjectStorage\BookChapterManager;
use AppBundle\ObjectStorage\OnlineSourceManager;
use AppBundle\ObjectStorage\BookClusterManager;
use AppBundle\ObjectStorage\BookSeriesManager;
use AppBundle\ObjectStorage\JournalManager;
use AppBundle\ObjectStorage\PhdManager;
use AppBundle\ObjectStorage\BibVariaManager;
use AppBundle\ObjectStorage\BlogManager;
use AppBundle\ObjectStorage\BlogPostManager;

class DefaultController extends AbstractController
{
    /**
     * @var array
     */
    const ENTITYTYPES = [
        'occurrence',
        'type',
        'manuscript',
        'person',
        'article',
        'book',
        'bookChapter',
        'onlineSource',
        'bookCluster',
        'bookSeries',
        'journal',
        'phd',
        'bibVaria',
        'blog',
        'blogPost',
    ];

    /**
     * @Route("/", name="homepage")
     * @param NewsEventService $newsEventService
     * @return Response
     */
    public function home(NewsEventService $newsEventService)
    {
        try {
            if ($this->isGranted('ROLE_VIEW_INTERNAL')) {
                $newsEvents = $newsEventService->getThree();
            } else {
                $newsEvents = $newsEventService->getThreePublic();
            }
        } catch (DBALException $e) {
            return $this->render(
                '@App/Home/home.html.twig',
                ['newsEvents' => []]
            );
        }
        return $this->render(
            '@App/Home/home.html.twig',
            ['newsEvents' => $newsEvents]
        );
    }

    /**
     * @Route("/sitemap.xml", name="sitemap")
     * @param OccurrenceManager $occurrenceManager
     * @param TypeManager $typeManager
     * @param ManuscriptManager $manuscriptManager
     * @param PersonManager $personManager
     * @param ArticleManager $articleManager
     * @param BookManager $bookManager
     * @param BookChapterManager $bookChapterManager
     * @param OnlineSourceManager $onlineSourceManager
     * @param BookClusterManager $bookClusterManager
     * @param BookSeriesManager $bookSeriesManager
     * @param JournalManager $journalManager
     * @param PhdManager $phdManager
     * @param BibVariaManager $bibVariaManager
     * @param BlogManager $blogManager
     * @param BlogPostManager $blogPostManager
     * @return Response
     */
    public function sitemap(
        OccurrenceManager $occurrenceManager,
        TypeManager $typeManager,
        ManuscriptManager $manuscriptManager,
        PersonManager $personManager,
        ArticleManager $articleManager,
        BookManager $bookManager,
        BookChapterManager $bookChapterManager,
        OnlineSourceManager $onlineSourceManager,
        BookClusterManager $bookClusterManager,
        BookSeriesManager $bookSeriesManager,
        JournalManager $journalManager,
        PhdManager $phdManager,
        BibVariaManager $bibVariaManager,
        BlogManager $blogManager,
        BlogPostManager $blogPostManager
    ) {
        $parts = [];
        foreach (self::ENTITYTYPES as $entityType) {
            var_dump(${$entityType . 'Manager'});
            $parts[$entityType] = ${$entityType . 'Manager'}->getLastModified();
        }

        return $this->render(
            '@App/Sitemap/sitemap.xml.twig',
            [
                'parts' => $parts,
            ]
        );
    }

    /**
     * @Route("/sitemap_{part}.xml", name="sitemap_part")
     * @param  string  $part
     * @param OccurrenceManager $occurrenceManager
     * @param TypeManager $typeManager
     * @param ManuscriptManager $manuscriptManager
     * @param PersonManager $personManager
     * @param ArticleManager $articleManager
     * @param BookManager $bookManager
     * @param BookChapterManager $bookChapterManager
     * @param OnlineSourceManager $onlineSourceManager
     * @param BookClusterManager $bookClusterManager
     * @param BookSeriesManager $bookSeriesManager
     * @param JournalManager $journalManager
     * @param PhdManager $phdManager
     * @param BibVariaManager $bibVariaManager
     * @param BlogManager $blogManager
     * @param BlogPostManager $blogPostManager
     * @return Response|NotFoundHttpException
     */
    public function sitemapPart(
        string $part,
        OccurrenceManager $occurrenceManager,
        TypeManager $typeManager,
        ManuscriptManager $manuscriptManager,
        PersonManager $personManager,
        ArticleManager $articleManager,
        BookManager $bookManager,
        BookChapterManager $bookChapterManager,
        OnlineSourceManager $onlineSourceManager,
        BookClusterManager $bookClusterManager,
        BookSeriesManager $bookSeriesManager,
        JournalManager $journalManager,
        PhdManager $phdManager,
        BibVariaManager $bibVariaManager,
        BlogManager $blogManager,
        BlogPostManager $blogPostManager
    ) {
        if (!in_array(
            $part,
            self::ENTITYTYPES
        )) {
            return $this->createNotFoundException('This sitemap could not be found');
        }

        $entities = ${$part . 'Manager'}->getAllSitemap();

        return $this->render(
            '@App/Sitemap/sitemap.xml.twig',
            [
                'entityType' => $part,
                'entities' => $entities,
            ]
        );
    }
}
