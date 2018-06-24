<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class TypeController extends Controller
{
    /**
     * @Route("/occurrences/{id}", name="type_get")
     * @param  int    $id type id
     * @param Request $request
     */
    public function getType(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
