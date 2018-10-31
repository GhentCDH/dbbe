<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'image_manager';

    /**
     * @Route("/images/{id}", name="image_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getImage(int $id, Request $request)
    {
        $images = $this->get('image_manager')->get([$id]);
        if (count($images) != 1) {
            throw $this->createNotFoundException('Image with id "' . $id . '" not found');
        }
        $image = $images[$id];

        if (!$image->getPublic()) {
            $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
        }
        try {
            return new BinaryFileResponse(
                $this->getParameter('kernel.project_dir') . '/'
                . $this->getParameter('image_directory') . '/'
                . $image->getFilename()
            );
        } catch (FileNotFoundException $e) {
            throw $this->createNotFoundException('Image with filename "' . $image->getFilename() . '" not found');
        }
    }

    /**
     * @Route("/images", name="image_post")
     * @Method("POST")
     * @param Request $request
     */
    public function post(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        $file = $request->files->get('file');
        try {
            $image = $this->get('image_manager')->getImageByFile($file);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($image->getJson());
    }
}
