<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\RoleManager;

class RoleController extends AbstractController
{
    public function __construct(RoleManager $roleManager)
    {
        $this->manager = $roleManager;
        $this->templateFolder = 'Person/';
    }

    /**
     * @Route("/roles", name="roles_get", methods={"GET"})
     * @param Request $request
     */
    public function getRoles(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                $this->manager->getAllRolesJson()
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/roles/edit", name="roles_edit", methods={"GET"})
     * @param Request $request
     */
    public function editRoles(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render(
            'Role/edit.html.twig',
            [
                'urls' => json_encode([
                    'roles_get' => $this->generateUrl('roles_get'),
                    'manuscript_deps_by_role' => $this->generateUrl('manuscript_deps_by_role', ['id' => 'role_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_role' => $this->generateUrl('occurrence_deps_by_role', ['id' => 'role_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_role' => $this->generateUrl('type_deps_by_role', ['id' => 'role_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'article_deps_by_role' => $this->generateUrl('article_deps_by_role', ['id' => 'role_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'book_deps_by_role' => $this->generateUrl('book_deps_by_role', ['id' => 'role_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_chapter_deps_by_role' => $this->generateUrl('book_chapter_deps_by_role', ['id' => 'role_id']),
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => 'book_chapter_id']),
                    'role_post' => $this->generateUrl('role_post'),
                    'role_put' => $this->generateUrl('role_put', ['id' => 'role_id']),
                    'role_delete' => $this->generateUrl('role_delete', ['id' => 'role_id']),
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                ]),
                'roles' => json_encode(
                    $this->manager->getAllRolesJson()
                ),
            ]
        );
    }

    /**
     * @Route("/roles", name="role_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function postRole(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $role = $this->manager->add(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($role->getJson());
    }

    /**
     * @Route("/roles/{id}", name="role_put", methods={"PUT"})
     * @param  int    $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function putRole(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $role = $this->manager->update($id, json_decode($request->getContent()));
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
     * @Route("/roles/{id}", name="role_delete", methods={"DELETE"})
     * @param  int    $id role id
     * @return JsonResponse
     */
    public function deleteRole(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $this->manager->delete($id);
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
