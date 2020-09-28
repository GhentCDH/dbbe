<?php

namespace AppBundle\Controller;

use Exception;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\ObjectStorage\VerseManager;
use AppBundle\Service\ElasticSearchService\ElasticVerseService;

use AppBundle\Utils\ArrayToJson;

class VerseController extends BaseController
{
    public function __construct(VerseManager $verseManager)
    {
        $this->manager = $verseManager;
        $this->templateFolder = '@App/Verse/';
    }

    /**
     * @Route("/verses/search", name="verse_search")
     * @Method("GET")
     * @param Request $request
     * @param ElasticVerseService $elasticVerseService
     * @return JsonResponse
     */
    public function getVerseSearch(Request $request, ElasticVerseService $elasticVerseService)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
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
     * @Route("/verse_variants/{groupId}", name="verse_variant_get")
     * @Method("GET")
     * @param int $groupId
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getVerseVariant(int $groupId, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
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
     * @Route("/verses/init", name="get_verse_init")
     * @Method("GET")
     * @param Request $request
     * @param ElasticVerseService $elasticVerseService
     * @return Response
     */
    public function getVerseInit(
        Request $request,
        ElasticVerseService $elasticVerseService
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

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
     * @Route("/verses/init", name="post_verse_init")
     * @Method("POST")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function postVerseInit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

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
