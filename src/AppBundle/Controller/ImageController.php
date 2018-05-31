<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

class ImageController extends Controller
{
    /**
     * @Route("/occ_images/{url}", name="image_show")
     * @Method("GET")
     * @param  string    $url image relative url
     * @param Request $request
     */
    public function getImage(string $url, Request $request)
    {
        // Let the 404 page handle the not found exception
        $image = $this->get('image_manager')->getImageByUrl($url);
        if (!$image->getPublic()) {
            $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
        }
        return new BinaryFileResponse($this->get('kernel')->getRootDir() . '/../images/' . $image->getUrl());
    }
}
