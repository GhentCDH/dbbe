<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BlogPostManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\RoleManager;

class BlogPostController extends BaseController
{
    public function __construct(BlogPostManager $blogPostManager)
    {
        $this->manager = $blogPostManager;
        $this->templateFolder = 'BlogPost/';
    }

    /**
     * @Route("/blogposts", name="blog_posts_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse|RedirectResponse
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
     * @Route("/blogposts/add", name="blog_post_add", methods={"GET"})
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    public function add(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @Route("/blogposts/{id}", name="blog_post_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all blog posts that have a dependency on a blog
     * (document_contains)
     * @Route("/blogposts/blogs/{id}", name="blog_post_deps_by_blog", methods={"GET"})
     * @param int $id blog id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBlog(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBlogDependencies');
    }

    /**
     * Get all blog posts that have a dependency on a person
     * (bibrole)
     * @Route("/blogposts/persons/{id}", name="blog_post_deps_by_person", methods={"GET"})
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all blog posts that have a dependency on a management collection
     * (reference)
     * @Route("/blogposts/managements/{id}", name="blog_post_deps_by_management", methods={"GET"})
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/blogposts", name="blog_post_post", methods={"POST"})
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
     * @Route("/blogposts/{id}", name="blog_post_put", methods={"PUT"})
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
     * @Route("/blogposts/{id}", name="blog_post_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/blogposts/{id}/edit", name="blog_post_edit", methods={"GET"})
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    public function edit(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'blog_post_get' => $this->generateUrl('blog_post_get', ['id' => $id == null ? 'blog_post_id' : $id]),
                    'blog_post_post' => $this->generateUrl('blog_post_post'),
                    'blog_post_put' => $this->generateUrl('blog_post_put', ['id' => $id == null ? 'blog_post_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'blogs_get' => $this->generateUrl('blogs_get'),
                    'bibliographies_search_blog' => $this->generateUrl('bibliographies_search', ['filters' => ['type' => '7']]),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    // @codingStandardsIgnoreEnd
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'blogPost' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('blogPost')
                ),
                'roles' => json_encode(
                    $roleManager->getByTypeJson('blogPost')
                ),
            ]
        );
    }
}
