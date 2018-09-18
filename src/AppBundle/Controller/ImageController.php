<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends Controller
{
    /**
     * @Route("/occ_images/{id}", name="image_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getImage(int $id, Request $request)
    {
        // Let the 404 page handle the not found exception
        $image = $this->get('image_manager')->getImageById($id);
        if (!$image->getPublic()) {
            $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
        }
        try {
            return new BinaryFileResponse($this->get('kernel')->getRootDir() . '/../images/' . $image->getUrl());
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException('Image with url "' . $image->getUrl()  . '" not found');
        }
    }
}
