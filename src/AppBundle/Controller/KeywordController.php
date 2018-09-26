<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class KeywordController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'keyword_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Keyword:';

    /**
     * @Route("/keywords/subject", name="keywords_subject_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAllSubjectKeywords(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            ArrayToJson::arrayToJson(
                $this->get(self::MANAGER)->getAllSubjectKeywords()
            )
        );
    }

    /**
     * @Route("/keywords/type", name="keywords_type_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAllTypeKeywords(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            ArrayToJson::arrayToJson(
                $this->get(self::MANAGER)->getAllTypeKeywords()
            )
        );
    }

    /**
     * @Route("/keywords/subject/edit", name="keywords_subject_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function subjectEdit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('keywords_subject_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_migrate_person' => $this->generateUrl('keyword_migrate_person', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get(self::MANAGER)->getAllSubjectKeywords()
                    )
                ),
                'persons' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('person_manager')->getAllHistoricalPersons()
                    )
                ),
                'isSubject' => json_encode(true),
            ]
        );
    }

    /**
     * @Route("/keywords/type/edit", name="keywords_type_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function typeEdit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('keywords_type_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get(self::MANAGER)->getAllTypeKeywords()
                    )
                ),
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
     * @param  int    $primaryId   keyword id (will be deleted)
     * @param  int    $secondaryId person id (will stay)
     * @param Request $request
     * @return JsonResponse
     */
    public function migrate(int $primaryId, int $secondaryId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        try {
            $object = $this
                ->get(static::MANAGER)
                ->migratePerson($primaryId, $secondaryId);
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
