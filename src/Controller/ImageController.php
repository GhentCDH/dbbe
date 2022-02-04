<?php

namespace App\Controller;

use Exception;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\ImageManager;

class ImageController extends BaseController
{
    public function __construct(ImageManager $imageManager)
    {
        $this->manager = $imageManager;
    }

    /**
     * @Route("/images/{id}", name="image_get", methods={"GET"})
     * @param int $id
     * @return BinaryFileResponse
     */
    public function getImage(int $id)
    {
        $images = $this->manager->get([$id]);
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
     * @Route("/images", name="image_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function post(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        $file = $request->files->get('file');
        try {
            $image = $this->manager->getImageByFile($file);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($image->getJson());
    }
}
