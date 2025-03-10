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
use App\Security\Roles;

class ImageController extends BaseController
{
    public function __construct(ImageManager $imageManager)
    {
        $this->manager = $imageManager;
    }

    /**
     * @param int $id
     * @return BinaryFileResponse
     */
    #[Route(path: '/images/{id}', name: 'image_get', methods: ['GET'])]
    public function getImage(int $id): BinaryFileResponse
    {
        $images = $this->manager->get([$id]);
        if (count($images) != 1) {
            throw $this->createNotFoundException('Image with id "' . $id . '" not found');
        }
        $image = $images[$id];

        if (!$image->getPublic()) {
            $this->denyAccessUnlessGranted(Roles::ROLE_VIEW_INTERNAL);
        }
        try {
            return new BinaryFileResponse(
                $this->getParameter('app.image_directory') . '/'
                . $image->getFilename()
            );
        } catch (FileNotFoundException $e) {
            throw $this->createNotFoundException('Image with filename "' . $image->getFilename() . '" not found');
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/images', name: 'image_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);
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
