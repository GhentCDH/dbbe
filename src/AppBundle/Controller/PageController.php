<?php

namespace AppBundle\Controller;

use AppBundle\Service\DatabaseService\PageService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PageController extends AbstractController
{
    /**
     * @Route("/pages/{slug}", name="page_get")
     * @Method("GET")
     * @param string $slug
     * @param PageService $pageService
     * @return Response
     */
    public function getPage(string $slug, PageService $pageService)
    {
        $page = $pageService->getBySlug($slug);
        if (empty($page)) {
            throw $this->createNotFoundException('The requested page does not exist');
        }
        return $this->render(
            '@App/Page/detail.html.twig',
            $page
        );
    }

    /**
     * @Route("/pages/{slug}", name="page_put")
     * @Method("PUT")
     * @param Request $request
     * @param string $slug
     * @param PageService $pageService
     * @return JsonResponse
     */
    public function put(Request $request, string $slug, PageService $pageService)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
        $page = $pageService->getBySlug($slug);
        if (empty($page)) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'This page does not exists']],
                Response::HTTP_NOT_FOUND
            );
        }
        $data = json_decode($request->getContent());
        if (!property_exists($data, 'title')
            || empty($data->title)
            || !is_string($data->title)
            || !property_exists($data, 'content')
            || empty($data->content)
            || !is_string($data->content)
            || !property_exists($data, 'display_navigation')
        ) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => 'Title, content and display navigation are mandatory.']],
                Response::HTTP_BAD_REQUEST
            );
        }
        $pageService->update(
            $this->get('security.token_storage')->getToken()->getUser()->getId(),
            $slug,
            $data->title,
            $data->content,
            $data->display_navigation ?? false
        );
        return new JsonResponse(
            json_encode(
                $pageService->getBySlug($slug)
            )
        );
    }

    /**
     * @Route("/pages/{slug}/edit", name="page_edit")
     * @Method("GET")
     * @param string $slug
     * @param PageService $pageService
     * @return Response
     */
    public function edit(string $slug, PageService $pageService)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $page = $pageService->getBySlug($slug);
        if (empty($page)) {
            throw $this->createNotFoundException('The requested page does not exist');
        }
        return $this->render(
            '@App/Page/edit.html.twig',
            [
                'slug' => $slug,
                'urls' => json_encode([
                    'page_get' => $this->generateUrl('page_get', ['slug' => $slug]),
                    'page_put' => $this->generateUrl('page_put', ['slug' => $slug]),
                    'page_image_get' => $this->generateUrl('page_image_get', ['name' => 'name']),
                    'page_image_post' => $this->generateUrl('page_image_post'),
                ]),
                'data' => json_encode($page),
            ]
        );
    }

    /**
     * @Route("/pages/images/{name}", name="page_image_get")
     * @Method("GET")
     * @param string $name
     * @return BinaryFileResponse
     */
    public function getImage(string $name)
    {
        try {
            return new BinaryFileResponse(
                $this->getParameter('kernel.project_dir') . '/'
                . $this->getParameter('page_image_directory') . '/'
                . $name
            );
        } catch (FileNotFoundException $e) {
            throw $this->createNotFoundException('Image with filename "' . $name . '" not found');
        }
    }

    /**
     * @Route("/pages/images", name="page_image_post")
     * @Method("POST")
     * @param  Request $request
     */
    public function postImage(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $file = $request->files->get('file');
        $filename = $file->getClientOriginalName();
        $imageDirectory = $this->getParameter('kernel.project_dir') . '/'
            . $this->getParameter('page_image_directory') . '/';

        // Upload file if no file with this name exists
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($imageDirectory . $filename)) {
            $file->move($imageDirectory, $filename);
        } else {
            $fileSystem->remove($file->getPathname());
        }
        return new JsonResponse([
            'name' => $filename,
        ]);
    }
}
