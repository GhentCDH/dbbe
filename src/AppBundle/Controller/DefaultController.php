<?php

namespace AppBundle\Controller;

use Doctrine\DBAL\DBALException;
use phpCAS;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        'online_source'
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
     * @return
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
     * @return
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

    /**
     * @Route("/login", name="login")
     * @param  Request $request
     */
    public function loginAction(Request $request)
    {
        $target = urlencode($this->generateUrl('force', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $url = 'https://'.$this->container->getParameter('cas_host') . '/login?service=';

        if ($request->get('close') == 'true') {
            return $this->redirect(
                $url . $target . urlencode('?referer=/close')
            );
        }
        if ($request->headers->get('referer') && $request->headers->get('referer') != '/login') {
            return $this->redirect(
                $url . $target . urlencode('?referer=' . urlencode($request->headers->get('referer')))
            );
        }
        if ($request->server->get('SCRIPT_URL') && $request->server->get('SCRIPT_URL') != '/login') {
            return $this->redirect(
                $url . $target . urlencode('?referer=' . urlencode($request->server->get('SCRIPT_URL')))
            );
        }
        return $this->redirect($url . $target);
    }

    /**
     * @Route("/logout", name="logout")
     * @param  Request $request
     */
    public function logoutAction(Request $request)
    {
        $target = urlencode($this->generateUrl('force', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $url = 'https://'.$this->container->getParameter('cas_host') . '/logout?url=';

        if ($request->headers->get('referer')) {
            phpCAS::logoutWithUrl($request->headers->get('referer'));
        } else {
            phpCAS::logout();
        }
    }

    /**
     * @Route("/force", name="force")
     * @param  Request $request
     */
    public function forceAction(Request $request)
    {
        if (!isset($_SESSION)) {
                session_start();
        }

        session_destroy();

        // Redirect so the security token is loaded properly
        if ($this->get('security.token_storage')->getToken()->getUserName() == '__NO_USER__') {
            return $this->redirect(
                $this->generateUrl('force') . '?referer=' . urlencode($request->headers->get('referer'))
            );
        }

        $this->userLogin();

        if ($request->query->get('referer')) {
            return $this->redirect(urldecode($request->query->get('referer')));
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }

    /**
     * Page that closes itself after login
     * @Route("/close", name="close")
     * @param  Request $request
     */
    public function closeAction(Request $request)
    {
        return new Response('<html><body onload="window.close();"></body></html>');
    }

    /**
     * Ensure user exists and last login info is up-to-date.
     */
    private function userLogin(): void
    {
        $userManager = $this->get('fos_user.user_manager');
        $token = $this->get('security.token_storage')->getToken();

        $user = $userManager->findUserByUsername($token->getUserName());
        $attributes = $token->getAttributes();

        $user->setLastLogin(new \DateTime());
        if ($user->getEmail() != $attributes['mail']) {
            $user->setEmail($attributes['mail']);
        }
        if ($user->getFullName() != ($attributes['givenname'] . ' ' . $attributes['surname'])) {
            $user->setFullName($attributes['givenname'] . ' ' . $attributes['surname']);
        }

        $userManager->updateUser($user);
    }
}
