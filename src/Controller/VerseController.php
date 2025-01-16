<?php

namespace App\Controller;

use Exception;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ElasticSearchService\ElasticVerseService;
use App\ObjectStorage\VerseManager;
use App\Security\Roles;
use App\Utils\ArrayToJson;

class VerseController extends BaseController
{
    public function __construct(VerseManager $verseManager)
    {
        $this->manager = $verseManager;
        $this->templateFolder = 'Verse/';
    }

    /**
     * @param Request $request
     * @param ElasticVerseService $elasticVerseService
     * @return JsonResponse
     */
    #[Route(path: '/verses/search', name: 'verse_search', methods: ['GET'])]
    public function getVerseSearch(Request $request, ElasticVerseService $elasticVerseService): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);
        $this->throwErrorIfNotJson($request);

        if ($request->query->get('verse') == null
            ||!is_string($request->query->get('verse'))
            || (
                $request->query->get('id') != null
                && !is_numeric($request->query->get('id'))
            )
        ) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => 'Bad data.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $results = $elasticVerseService->searchVerse(
            $request->query->get('verse'),
            $request->query->get('id')
        );

        return new JsonResponse($results);
    }

    /**
     * @param int $groupId
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/verse_variants/{groupId}', name: 'verse_variant_get', methods: ['GET'])]
    public function getVerseVariant(int $groupId, Request $request): JsonResponse|Response
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
            try {
                $group = $this->manager->getByGroup($groupId);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse(ArrayToJson::arrayToJson($group));
        } else {
            // Let the 404 page handle the not found exception
            $group = $this->manager->getByGroup($groupId);

            return $this->render(
                $this->templateFolder . 'variant.html.twig',
                ['group' => $group]
            );
        }
    }

    /**
     * @param Request $request
     * @param ElasticVerseService $elasticVerseService
     * @return Response
     */
    #[Route(path: '/verses/init', name: 'get_verse_init', methods: ['GET'])]
    public function getVerseInit(
        Request $request,
        ElasticVerseService $elasticVerseService
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);

        $page = (int)$request->get('page');

        $groups = $elasticVerseService->initVerseGroups($page);

        return $this->render(
            $this->templateFolder . 'init.html.twig',
            [
                'groups' => $groups,
                'page' => $page,
            ]
        );
    }

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    #[Route(path: '/verses/init', name: 'post_verse_init', methods: ['POST'])]
    public function postVerseInit(Request $request): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);

        $data = $request->request->all();

        $page = $data['page'];
        unset($data['page']);

        $verses = [];
        foreach ($data as $verseId => $groupId) {
            $verse = ['id' => $verseId];
            if ($groupId != null) {
                $verse['groupId'] = $groupId;
            }
            $verses[] = $verse;
        }

        $number = count($verses);

        $first = array_shift($verses);
        $updateData = json_decode(json_encode(['linkVerses' => $verses]));

        $new = $this->manager->update($first['id'], $updateData);

        return $this->render(
            $this->templateFolder . 'init_confirm.html.twig',
            [
                'number' => $number,
                'groupId' => $new->getGroupId(),
                'page' => $page,
            ]
        );
    }
}
