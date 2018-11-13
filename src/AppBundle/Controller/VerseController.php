<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class VerseController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'verse_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Verse:';

    /**
     * @Route("/verses/search", name="verse_search")
     * @Method("GET")
     * @param Request $request
     */
    public function getVerseSearch(Request $request)
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

        $results = $this->get('verse_elastic_service')->searchVerse(
            $request->query->get('verse'),
            $request->query->get('id')
        );

        return new JsonResponse($results);
    }

    /**
     * @Route("/verse_variants/{groupId}", name="verse_variant_get")
     * @Method("GET")
     * @param int     $groupId
     * @param Request $request
     */
    public function getVerseVariant(int $groupId, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $group = $this->get(static::MANAGER)->getByGroup($groupId);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse(ArrayToJson::arrayToJson($group));
        } else {
            // Let the 404 page handle the not found exception
            $group = $this->get(static::MANAGER)->getByGroup($groupId);

            return $this->render(
                static::TEMPLATE_FOLDER . 'variant.html.twig',
                ['group' => $group]
            );
        }
    }

    /**
     * @Route("/verses/init", name="get_verse_init")
     * @Method("GET")
     * @param Request $request
     */
    public function getVerseInit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $page = (int)$request->get('page');

        $groups = $this->get('verse_elastic_service')->initVerseGroups($page);

        return $this->render(
            static::TEMPLATE_FOLDER . 'init.html.twig',
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

        $first = array_shift($verses);
        $updateData = json_decode(json_encode(['linkVerses' => $verses]));

        $this->get('verse_manager')->update($first['id'], $updateData);

        return $this->redirectToRoute('get_verse_init', ['page' => $page]);
    }
}
