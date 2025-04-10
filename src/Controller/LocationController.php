<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\LocationManager;
use App\Security\Roles;

class LocationController extends BaseController
{
    public function __construct(LocationManager $locationManager)
    {
        $this->manager = $locationManager;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/locations', name: 'locations_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            if (!empty($request->query->get('type'))
                && $request->query->get('type') == 'manuscript'
            ) {
                return new JsonResponse(
                    $this->manager->getByTypeJson('manuscript')
                );
            } else {
                return new JsonResponse(
                    $this->manager->getByTypeJson('location')
                );
            }
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @return Response
     */
    #[Route(path: '/locations/edit', name: 'locations_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Location/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'locations_get' => $this->generateUrl('locations_get'),
                    'manuscript_deps_by_institution' => $this->generateUrl('manuscript_deps_by_institution', ['id' => 'institution_id']),
                    'manuscript_deps_by_collection' => $this->generateUrl('manuscript_deps_by_collection', ['id' => 'collection_id']),
                    'online_source_deps_by_institution' => $this->generateUrl('online_source_deps_by_institution', ['id' => 'institution_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'online_source_get' => $this->generateUrl('online_source_get', ['id' => 'online_source_id']),
                    'regions_edit' => $this->generateUrl('regions_edit'),
                    'region_put' => $this->generateUrl('region_put', ['id' => 'region_id']),
                    'library_post' => $this->generateUrl('library_post'),
                    'library_put' => $this->generateUrl('library_put', ['id' => 'library_id']),
                    'library_delete' => $this->generateUrl('library_delete', ['id' => 'library_id']),
                    'collection_post' => $this->generateUrl('collection_post'),
                    'collection_put' => $this->generateUrl('collection_put', ['id' => 'collection_id']),
                    'collection_delete' => $this->generateUrl('collection_delete', ['id' => 'collection_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'locations' => json_encode(
                    $this->manager->getByTypeJson('location')
                ),
            ]
        );
    }
}
