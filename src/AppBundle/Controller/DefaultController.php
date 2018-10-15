<?php

namespace AppBundle\Controller;

use phpCAS;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @param  Request $request
     */
    public function home(Request $request)
    {
        return $this->render(
            'AppBundle:Home:home.html.twig'
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
