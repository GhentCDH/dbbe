<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BlogPostController extends EditController
{
    /**
     * @var string
     */
    const MANAGER = 'blog_post_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:BlogPost:';

    /**
     * @Route("/blogposts", name="blog_posts_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/blogposts/add", name="blog_post_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/blogposts/{id}", name="blog_post_get")
     * @Method("GET")
      * @param int     $id
      * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all blog posts that have a dependency on a blog
     * (document_contains)
     * @Route("/blogposts/blogs/{id}", name="blog_post_deps_by_blog")
     * @Method("GET")
     * @param  int    $id blog id
     * @param Request $request
     */
    public function getDepsByBlog(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBlogDependencies');
    }

    /**
     * Get all blog posts that have a dependency on a management collection
     * (reference)
     * @Route("/blogposts/managements/{id}", name="blog_post_deps_by_management")
     * @Method("GET")
     * @param  int    $id management id
     * @param Request $request
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/blogposts", name="blog_post_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Blog post added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/blogposts/{id}", name="blog_post_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Blog post data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/blogposts/{id}", name="blog_post_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/blogposts/{id}/edit", name="blog_post_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'blog_post_get' => $this->generateUrl('blog_post_get', ['id' => $id == null ? 'blog_post_id' : $id]),
                    'blog_post_post' => $this->generateUrl('blog_post_post'),
                    'blog_post_put' => $this->generateUrl('blog_post_put', ['id' => $id == null ? 'blog_post_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'blogs_get' => $this->generateUrl('blogs_get'),
                    'bibliographies_search' => $this->generateUrl('bibliographies_search'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    // @codingStandardsIgnoreEnd
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'blogPost' => empty($id)
                        ? null
                        : $this->get(self::MANAGER)->getFull($id)->getJson(),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getByTypeJson('blogPost')
                ),
                'roles' => json_encode(
                    $this->get('role_manager')->getByTypeJson('blogPost')
                ),
            ]
        );
    }
}
