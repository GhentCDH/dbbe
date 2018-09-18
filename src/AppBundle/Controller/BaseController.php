<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Utils\ArrayToJson;

class BaseController extends Controller
{
    /**
     * @param Request $request
     */
    public function add(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->edit(null, $request);
    }

    /**
     * @param int     $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $object = $this->get(static::MANAGER)->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($object->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $object = $this->get(static::MANAGER)->getFull($id);
            return $this->render(
                static::TEMPLATE_FOLDER . 'detail.html.twig',
                [$object::CACHENAME => $object]
            );
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            ArrayToJson::arrayToJson(
                $this->get(static::MANAGER)->getAll()
            )
        );
    }

    /**
     * @param  int     $id
     * @param  Request $request
     * @param  string  $method The method to be invoked on the manager to retrieve the objects
     * @return JsonResponse
     */
    public function getDependencies(int $id, Request $request, string $method)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        $objects = $this
            ->get(static::MANAGER)
            ->{$method}($id);
        return new JsonResponse(ArrayToJson::arrayToShortJson($objects));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $object = $this
                ->get(static::MANAGER)
                ->add(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($object->getJson());
    }

    /**
     * @param  int    $primaryId   first object id (will stay)
     * @param  int    $secondaryId second object id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $object = $this
                ->get(static::MANAGER)
                ->merge($primaryId, $secondaryId);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse($object->getJson());
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $object = $this
                ->get(static::MANAGER)
                ->update($id, json_decode($request->getContent()));
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

        return new JsonResponse($object->getJson());
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $this
                ->get(static::MANAGER)
                ->delete($id);
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

    /**
     * Throws a BadRequestHttpException if the accept header is not set to application/json
     * @param Request $request
     */
    protected function throwErrorIfNotJson($request): void
    {
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }
}