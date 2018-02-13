<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class LocationController extends Controller
{
    /**
     * @Route("/locations", name="locations")
     */
    public function getLocations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        // TODO: actually return locations with possibility to edit
    }
}
