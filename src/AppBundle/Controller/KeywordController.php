<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get(self::MANAGER)->getAllSubjectKeywords()
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
