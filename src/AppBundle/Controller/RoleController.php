<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Utils\ArrayToJson;

class RoleController extends Controller
{
    /**
     * @Route("/roles", name="roles_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getRoles(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('role_manager')->getAllRoles()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/roles/edit", name="roles_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editRoles(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render(
            'AppBundle:Role:edit.html.twig',
            [
                'urls' => json_encode([
                    'roles_get' => $this->generateUrl('roles_get'),
                    'manuscript_deps_by_role' => $this->generateUrl('manuscript_deps_by_role', ['id' => 'role_id']),
                    // TODO: occurrences, types
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'role_post' => $this->generateUrl('role_post'),
                    'role_put' => $this->generateUrl('role_put', ['id' => 'role_id']),
                    'role_delete' => $this->generateUrl('role_delete', ['id' => 'role_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('role_manager')->getAllRoles()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/roles", name="role_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postRole(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $role = $this
                ->get('role_manager')
                ->addRole(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($role->getJson());
    }

    /**
     * @Route("/roles/{id}", name="role_put")
     * @Method("PUT")
     * @param  int    $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function putRole(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $role = $this
                ->get('role_manager')
                ->updateRole($id, json_decode($request->getContent()));
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

        return new JsonResponse($role->getJson());
    }

    /**
     * @Route("/roles/{id}", name="role_delete")
     * @Method("DELETE")
     * @param  int    $id role id
     * @return JsonResponse
     */
    public function deleteRole(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $this
                ->get('role_manager')
                ->delRole($id);
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
