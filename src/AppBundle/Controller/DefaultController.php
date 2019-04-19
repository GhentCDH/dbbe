<?php

namespace AppBundle\Controller;

use Doctrine\DBAL\DBALException;
use phpCAS;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
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
        'book_chapter',
        'online_source',
    ];

    /**
     * @Route("/", name="homepage")
     * @return Response
     */
    public function home()
    {
        try {
            if ($this->isGranted('ROLE_VIEW_INTERNAL')) {
                $newsEvents = $this->get('news_event_service')->getThree();
            } else {
                $newsEvents = $this->get('news_event_service')->getThreePublic();
            }
        }
        catch (DBALException $e) {
            return $this->render(
                'AppBundle:Home:home.html.twig',
                ['newsEvents' => []]
            );
        }
        return $this->render(
            'AppBundle:Home:home.html.twig',
            ['newsEvents' => $newsEvents]
        );
    }

    /**
     * @Route("/sitemap.xml", name="sitemap")
     * @param  Request $request
     * @return Response
     */
    public function sitemap(Request $request)
    {
        $parts = [];
        foreach (self::ENTITYTYPES as $entityType) {
            $parts[$entityType] = $this->get($entityType . '_manager')->getLastModified();
        }

        return $this->render(
            'AppBundle:Sitemap:sitemap.xml.twig',
            [
                'parts' => $parts,
            ]
        );
    }

    /**
     * @Route("/sitemap_{part}.xml", name="sitemap_part")
     * @param  string  $part
     * @param  Request $request
     * @return Response
     */
    public function sitemapPart(string $part, Request $request)
    {
        if (!in_array(
            $part,
            self::ENTITYTYPES
        )) {
            return $this->createNotFoundException('This sitemap could not be found');
        }

        $entities = $this->get($part . '_manager')->getAllSitemap();

        return $this->render(
            'AppBundle:Sitemap:sitemapPart.xml.twig',
            [
                'entityType' => $part,
                'entities' => $entities,
            ]
        );
    }
}
