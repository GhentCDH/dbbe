<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class BookChapterController extends Controller
{
    /**
     * @Route("/bookchapters/{id}", name="book_chapter_get")
     * @Method("GET")
     */
    public function getBookChapter(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
