<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class CustomRedirectController extends BaseController
{
    #[Route(path: '/post_login_redirect', name: 'post_login_redirect', methods: ['GET'])]
    public function postLoginRedirect(SessionInterface $session): RedirectResponse
    {
        $redirectUrl = $session->get('pre_login_redirect', $this->generateUrl('homepage'));

        $session->remove('pre_login_redirect');

        return $this->redirect($redirectUrl);
    }
}

