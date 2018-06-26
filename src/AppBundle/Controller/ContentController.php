<?php

namespace AppBundle\Controller;

use Exception;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class ContentController extends Controller
{
    /**
     * @Route("/contents/contents/{id}", name="contents_by_content")
     * @param int $id The content id
     * @param Request $request
     */
    public function getContentsByContent(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $contentsWithParents = $this
                ->get('content_manager')
                ->getContentsWithParentsByContent($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($contentsWithParents));
        }
        throw new Exception('Not implemented.');
    }

    /**
     * @Route("/contents", name="contents_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getContents(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('content_manager')->getAllContentsWithParents()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/contents/edit", name="contents_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editContents(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Content:edit.html.twig',
            [
                'urls' => json_encode([
                    'contents_get' => $this->generateUrl('contents_get'),
                    'manuscript_deps_by_content' => $this->generateUrl('manuscript_deps_by_content', ['id' => 'content_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'contents_by_content' => $this->generateUrl('contents_by_content', ['id' => 'content_id']),
                    'content_post' => $this->generateUrl('content_post'),
                    'content_merge' => $this->generateUrl('content_merge', ['primary' => 'primary_id', 'secondary' => 'secondary_id']),
                    'content_put' => $this->generateUrl('content_put', ['id' => 'content_id']),
                    'content_delete' => $this->generateUrl('content_delete', ['id' => 'content_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'contents' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('content_manager')->getAllContentsWithParents()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/contents", name="content_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postContent(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $contentWithParents = $this
                ->get('content_manager')
                ->addContentWithParents(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($contentWithParents->getJson());
    }

    /**
     * @Route("/contents/{primary}/{secondary}", name="content_merge")
     * @Method("PUT")
     * @param  int    $primary first content id (will stay)
     * @param  int    $secondary second content id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function mergeContents(int $primary, int $secondary, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $contentWithParents = $this
                ->get('content_manager')
                ->mergeContentsWithParents($primary, $secondary);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse($contentWithParents->getJson());
    }

    /**
     * @Route("/contents/{id}", name="content_put")
     * @Method("PUT")
     * @param  int    $id content id
     * @param Request $request
     * @return JsonResponse
     */
    public function putContent(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $contentWithParents = $this
                ->get('content_manager')
                ->updateContentWithParents($id, json_decode($request->getContent()));
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($contentWithParents->getJson());
    }

    /**
     * @Route("/contents/{id}", name="content_delete")
     * @Method("DELETE")
     * @param  int    $id content id
     * @return JsonResponse
     */
    public function deleteContent(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('content_manager')
                ->delContent($id);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
