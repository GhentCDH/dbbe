<?php

namespace App\Controller;

use Doctrine\DBAL\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\DatabaseService\NewsEventService;
use App\ObjectStorage\ElasticManagers;
use App\ObjectStorage\OccurrenceManager;
use App\ObjectStorage\TypeManager;
use App\ObjectStorage\ManuscriptManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\ArticleManager;
use App\ObjectStorage\BookManager;
use App\ObjectStorage\BookChapterManager;
use App\ObjectStorage\OnlineSourceManager;
use App\ObjectStorage\BookClusterManager;
use App\ObjectStorage\BookSeriesManager;
use App\ObjectStorage\JournalManager;
use App\ObjectStorage\PhdManager;
use App\ObjectStorage\BibVariaManager;
use App\ObjectStorage\BlogManager;
use App\ObjectStorage\BlogPostManager;
use App\Security\Roles;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="homepage", methods={"GET"})
     * @param NewsEventService $newsEventService
     * @return Response
     */
    public function home(NewsEventService $newsEventService)
    {
        try {
            if ($this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $newsEvents = $newsEventService->getThree();
            } else {
                $newsEvents = $newsEventService->getThreePublic();
            }
        } catch (DBALException $e) {
            return $this->render(
                'Home/home.html.twig',
                ['newsEvents' => []]
            );
        }
        return $this->render(
            'Home/home.html.twig',
            ['newsEvents' => $newsEvents]
        );
    }

    /**
     * @Route("/sitemap.xml", name="sitemap", methods={"GET"})
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
        foreach (array_keys(ElasticManagers::MANAGERS) as $entityType) {
            $entityTypeCC = lcfirst(str_replace('_', '', ucwords($entityType, '_')));
            $parts[$entityType] = ${$entityTypeCC . 'Manager'}->getLastModified();
        }

        return $this->render(
            'Sitemap/sitemap.xml.twig',
            [
                'parts' => $parts,
            ]
        );
    }

    /**
     * @Route("/sitemap_{part}.xml", name="sitemap_part", methods={"GET"})
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
     * @return Response
     * @throws NotFoundHttpException
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
            array_keys(ElasticManagers::MANAGERS)
        )) {
            throw $this->createNotFoundException('This sitemap could not be found');
        }

        $partCC = lcfirst(str_replace('_', '', ucwords($part, '_')));
        $entities = ${$partCC . 'Manager'}->getAllSitemap();

        return $this->render(
            'Sitemap/sitemapPart.xml.twig',
            [
                'entityType' => $part,
                'entities' => $entities,
            ]
        );
    }
}
