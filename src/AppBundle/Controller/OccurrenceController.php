<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class OccurrenceController extends Controller
{
    /**
     * @Route("/occurrences/{id}", name="occurrence_show")
     */
    public function getOccurrence(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
