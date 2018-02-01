<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PersonController extends Controller
{
    /**
     * @Route("/persons/{id}", name="person_show")
     */
    public function getPerson(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
