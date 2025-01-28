<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class CustomAuthController extends BaseController
{
    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function login(Request $request, SessionInterface $session): RedirectResponse
    {
        $referer = $request->headers->get('referer', $request->getUri());
        $session->set('pre_login_redirect', $referer);

        return $this->redirectToRoute('idci_keycloak_security_auth_connect');
    }

}