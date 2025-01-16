<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\OriginManager;
use App\Security\Roles;

class OriginController extends BaseController
{
    public function __construct(OriginManager $originManager)
    {
        $this->manager = $originManager;
    }

    /**
     * @param Request $request
     */
    #[Route(path: '/origins', name: 'origins_get', methods: ['GET'])]
    public function getOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            if ($request->query->get('type') == 'person') {
                // person edit page
                return new JsonResponse(
                    $this->manager->getByTypeShortJson('person')
                );
            } elseif ($request->query->get('type') == 'manuscript') {
                // manuscript edit page
                return new JsonResponse(
                    $this->manager->getByTypeShortJson('manuscript')
                );
            } else {
                // origins edit page
                return new JsonResponse(
                    // origins for manuscript is a superset of origins for persons
                    $this->manager->getByTypeJson('manuscript')
                );
            }
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    #[Route(path: '/origins/edit', name: 'origins_edit', methods: ['GET'])]
    public function edit()
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Origin/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'origins_get' => $this->generateUrl('origins_get'),
                    'manuscript_deps_by_institution' => $this->generateUrl('manuscript_deps_by_institution', ['id' => 'institution_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'regions_edit' => $this->generateUrl('regions_edit'),
                    'region_put' => $this->generateUrl('region_put', ['id' => 'region_id']),
                    'monastery_post' => $this->generateUrl('monastery_post'),
                    'monastery_put' => $this->generateUrl('monastery_put', ['id' => 'monastery_id']),
                    'monastery_delete' => $this->generateUrl('monastery_delete', ['id' => 'monastery_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'origins' => json_encode(
                    // origins for manuscript is a superset of origins for persons
                    $this->manager->getByTypeJson('manuscript')
                ),
            ]
        );
    }
}
