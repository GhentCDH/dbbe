<?php

namespace AppBundle\Controller;

use Exception;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\ObjectStorage\KeywordManager;
use AppBundle\ObjectStorage\PersonManager;

class KeywordController extends BaseController
{
    public function __construct(KeywordManager $keywordManager)
    {
        $this->manager = $keywordManager;
        $this->templateFolder = '@App/Keyword/';
    }

    /**
     * @Route("/keywords", name="subjects_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllSubjects(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getByTypeJson('subject')
        );
    }

    /**
     * @Route("/tags", name="tags_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllTags(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getByTypeJson('type')
        );
    }

    /**
     * @Route("/keywords/edit", name="subjects_edit")
     * @Method("GET")
     * @param PersonManager $personManager
     * @return Response
     */
    public function subjectEdit(PersonManager $personManager)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('subjects_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_migrate_person' => $this->generateUrl('keyword_migrate_person', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode($this->manager->getByTypeJson('subject')),
                'persons' => json_encode($personManager->getAllHistoricalShortJson()),
                'isSubject' => json_encode(true),
            ]
        );
    }

    /**
     * @Route("/tags/edit", name="tags_edit")
     * @Method("GET")
     * @return Response
     */
    public function tagEdit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('tags_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode($this->manager->getByTypeJson('type')),
                'persons' => json_encode([]),
                'isSubject' => json_encode(false),
            ]
        );
    }

    /**
     * @Route("/keywords", name="keyword_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/keywords/{id}", name="keyword_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/keywords/{primaryId}/persons/{secondaryId}", name="keyword_migrate_person")
     * @Method("PUT")
     * @param int $primaryId keyword id (will be deleted)
     * @param int $secondaryId person id (will stay)
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function migrate(int $primaryId, int $secondaryId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $this->manager->migratePerson($primaryId, $secondaryId);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/keywords/{id}", name="keyword_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
