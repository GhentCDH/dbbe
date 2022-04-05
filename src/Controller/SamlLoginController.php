<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class SamlLoginController extends AbstractController
{
    protected $samlAuth;

    public function __construct(\OneLogin\Saml2\Auth $samlAuth)
    {
        $this->samlAuth = $samlAuth;
    }

    // Modification from loginAction in Hslavich\OneloginSamlBundle\Controller\SamlController
    // The modification allows the referer to be used as target path after login.

    /**
     * @Route("/dbbe_login", name="dbbe_login", methods={"GET"})
     * @param NewsEventService $newsEventService
     * @return Response
     */
    public function loginAction(Request $request)
    {
        $authErrorKey = Security::AUTHENTICATION_ERROR;
        $session = $targetPath = $error = $referer = null;

        if ($request->hasSession()) {
            $session = $request->getSession();
            $firewallName = array_slice(explode('.', trim($request->attributes->get('_firewall_context'))), -1)[0];
            $targetPath = $session->get('_security.'.$firewallName.'.target_path');
        }

        if ($request->attributes->has($authErrorKey)) {
            $error = $request->attributes->get($authErrorKey);
        } elseif (null !== $session && $session->has($authErrorKey)) {
            $error = $session->get($authErrorKey);
            $session->remove($authErrorKey);
        }

        if ($error instanceof \Exception) {
            throw new \RuntimeException($error->getMessage());
        }

        if ($targetPath == null) {
            $referer = $request->headers->get('referer');
            if ($referer != null) {
                $targetPath = $referer;
            }
        }

        $this->samlAuth->login($targetPath);
    }
}