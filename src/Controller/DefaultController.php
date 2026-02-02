<?php

namespace App\Controller;

use App\ElasticSearchService\ElasticManuscriptService;
use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticPersonService;
use App\ElasticSearchService\ElasticTypeService;
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
     * @param NewsEventService $newsEventService
     * @return Response
     */
    #[Route(path: '/', name: 'homepage', methods: ['GET'])]
    public function home(
        NewsEventService $newsEventService,
        ElasticOccurrenceService $elasticOccurrenceService,
        ElasticPersonService $elasticPersonService,
        ElasticTypeService $elasticTypeService,
        ElasticManuscriptService $elasticManuscriptService
    ): Response
    {
        try {
            if ($this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $newsEvents = $newsEventService->getThree();
            } else {
                $newsEvents = $newsEventService->getThreePublic();
            }
        } catch (DBALException $e) {
            $newsEvents = [];
        }

        $latestItems = [];

        // latest occurrences
        $occRes = $elasticOccurrenceService->searchAndAggregate(
            [
                'limit'     => 3,
                'page'      => 1,
                'orderBy'   => ['created'],
                'ascending' => 0,
            ],
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );
        foreach (($occRes['data'] ?? []) as $occ) {
            $latestItems[] = [
                'id'    => $occ['id'],
                'type'  => 'Occurrence',
                'label' => $occ['incipit'] ?? '[no incipit]',
                'date'  => isset($occ['created']) ? new \DateTime($occ['created']) : null,
                'route' => 'occurrence_get',
            ];
        }

        // latest persons
        $personRes = $elasticPersonService->searchAndAggregate(
            [
                'limit'     => 3,
                'page'      => 1,
                'orderBy'   => ['created'],
                'ascending' => 0,
            ],
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );
        foreach (($personRes['data'] ?? []) as $person) {
            $latestItems[] = [
                'id'    => $person['id'],
                'type'  => 'Person',
                'label' => $person['name'] ?? '[unnamed person]',
                'date'  => isset($person['created']) ? new \DateTime($person['created']) : null,
                'route' => 'person_get',
            ];
        }

        // latest types
        $typeRes = $elasticTypeService->searchAndAggregate(
            [
                'limit'     => 3,
                'page'      => 1,
                'orderBy'   => ['created'],
                'ascending' => 0,
            ],
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );
        foreach (($typeRes['data'] ?? []) as $type) {
            $latestItems[] = [
                'id'    => $type['id'],
                'type'  => 'Type',
                'label' => $type['incipit'] ?? '[no incipit]',
                'date'  => isset($type['created']) ? new \DateTime($type['created']) : null,
                'route' => 'type_get',
            ];
        }

        // latest manuscripts
        $msRes = $elasticManuscriptService->searchAndAggregate(
            [
                'limit'     => 3,
                'page'      => 1,
                'orderBy'   => ['created'],
                'ascending' => 0,
            ],
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );
        foreach (($msRes['data'] ?? []) as $ms) {
            $latestItems[] = [
                'id'    => $ms['id'],
                'type'  => 'Manuscript',
                'label' => $ms['name'] ?? '[unnamed manuscript]',
                'date'  => isset($ms['created']) ? new \DateTime($ms['created']) : null,
                'route' => 'manuscript_get',
            ];
        }

        // sort everything by date desc
        usort($latestItems, function ($a, $b) {
            return ($b['date']?->getTimestamp() ?? 0)
                <=> ($a['date']?->getTimestamp() ?? 0);
        });

        return $this->render('Home/home.html.twig', [
            'newsEvents'  => $newsEvents,
            'latestItems' => $latestItems,
        ]);
    }


    /**
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
    #[Route(path: '/sitemap.xml', name: 'sitemap', methods: ['GET'])]
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
    #[Route(path: '/sitemap_{part}.xml', name: 'sitemap_part', methods: ['GET'])]
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
