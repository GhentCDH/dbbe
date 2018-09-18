<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class ContentController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'content_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Content:';

    /**
     * @Route("/contents", name="contents_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * Get all contents that have a dependency on a content
     * (genre -> idparentgenre)
     * @Route("/contents/contents/{id}", name="content_deps_by_content")
     * @Method("GET")
     * @param int $id The content id
     * @param Request $request
     */
    public function getDepsByContent(int $id, Request $request)
    {
        $this->getDependencies($id, $request, 'getContentDependencies');
    }

    /**
     * @Route("/contents/edit", name="contents_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editContents(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Content:edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'contents_get' => $this->generateUrl('contents_get'),
                    'manuscript_deps_by_content' => $this->generateUrl('manuscript_deps_by_content', ['id' => 'content_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'content_deps_by_content' => $this->generateUrl('content_deps_by_content', ['id' => 'content_id']),
                    'content_post' => $this->generateUrl('content_post'),
                    'content_merge' => $this->generateUrl('content_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'content_put' => $this->generateUrl('content_put', ['id' => 'content_id']),
                    'content_delete' => $this->generateUrl('content_delete', ['id' => 'content_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'contents' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('content_manager')->getAll()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/contents", name="content_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/contents/{primaryId}/{secondaryId}", name="content_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first content id (will stay)
     * @param  int    $secondaryId second content id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/contents/{id}", name="content_put")
     * @Method("PUT")
     * @param  int    $id content id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/contents/{id}", name="content_delete")
     * @Method("DELETE")
     * @param  int    $id content id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
