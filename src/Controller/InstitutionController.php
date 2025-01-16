<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\InstitutionManager;
use App\Security\Roles;
use App\Utils\ArrayToJson;

class InstitutionController extends BaseController
{
    public function __construct(InstitutionManager $institutionManager)
    {
        $this->manager = $institutionManager;
    }

    /**
     * @param int $id The region id
     * @param Request $request
     */
    #[Route(path: '/institutions/regions/{id}', name: 'institution_deps_by_region')]
    public function getInstitutionsByRegion(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $institutions = $this->manager->getInstitutionsByRegion($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($institutions));
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @param Request $request
     * @param bool $library Indicates whether the institution is a library
     * @param bool $monastery Indicates whether the institution is a library
     * @return JsonResponse
     */
    #[Route(path: '/institutions', name: 'institution_post', methods: ['POST'])]
    public function postInstitution(Request $request, bool $library = false, bool $monastery = false): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);

        try {
            $institution = $this->manager->add(json_decode($request->getContent()), $library, $monastery);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($institution->getJson());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/libraries', name: 'library_post', methods: ['POST'])]
    public function postLibrary(Request $request): JsonResponse
    {
        return $this->postInstitution($request, true);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/monasteries', name: 'monastery_post', methods: ['POST'])]
    public function postMonastery(Request $request): JsonResponse
    {
        return $this->postInstitution($request, false, true);
    }

    /**
     * @param  int    $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/institutions/{id}', name: 'institution_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Institution successfully saved.');
        }

        return $response;
    }

    /**
     * @param  int    $id library id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/libraries/{id}', name: 'library_put', methods: ['PUT'])]
    public function putLibrary(int $id, Request $request): JsonResponse
    {
        return $this->put($id, $request);
    }

    /**
     * @param  int    $id monastery id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/monasteries/{id}', name: 'monastery_put', methods: ['PUT'])]
    public function putMonastery(int $id, Request $request): JsonResponse
    {
        return $this->put($id, $request);
    }

    /**
     * @param int $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/institutions/{id}', name: 'institution_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param int $id library id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/libraries/{id}', name: 'library_delete', methods: ['DELETE'])]
    public function deleteLibrary(int $id, Request $request): JsonResponse
    {
        return $this->delete($id, $request);
    }

    /**
     * @param int $id monastery id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/monasteries/{id}', name: 'monastery_delete', methods: ['DELETE'])]
    public function deleteMonastery(int $id, Request $request): JsonResponse
    {
        return $this->delete($id, $request);
    }
}
