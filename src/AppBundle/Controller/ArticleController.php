<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class ArticleController extends Controller
{
    /**
     * @Route("/articles/{id}", name="article_get")
     * @Method("GET")
     */
    public function getArticle(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
