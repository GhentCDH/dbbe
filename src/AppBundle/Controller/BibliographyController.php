<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class BibliographyController extends Controller
{
    /**
     * @Route("/bibliography/{id}", name="bibliography_get")
     * @Method("GET")
     */
    public function getBibliography(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
