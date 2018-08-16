<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class OnlineSourceController extends Controller
{
    /**
     * @Route("/onlinesource/{id}", name="online_source_get")
     * @Method("GET")
     */
    public function getOnlineSource(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
