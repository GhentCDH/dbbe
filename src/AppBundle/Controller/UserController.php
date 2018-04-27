<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class UserController extends Controller
{
    /**
     * @Route("/admin/users")
     * @Method("GET")
     * @param Request $request
     */
    public function getUsers(Request $request)
    {
        if (in_array('application/json', explode(', ', $request->headers->get('Accept')))) {
            // JSON
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository('AppBundle:User');
            $userCount = count($repo->findAll());
            switch ($request->query->get('orderBy')) {
                case 'username':
                    $orderBy = 'usernameCanonical';
                    break;
                case 'email':
                    $orderBy = 'emailCanonical';
                    break;
                case 'full name':
                    $orderBy = 'fullName';
                    break;
                case 'status':
                    $orderBy = 'enabled';
                    break;
                case 'last login':
                    $orderBy = 'lastLogin';
                    break;
                default:
                    $orderBy = $request->query->get('orderBy');
                    break;
            }
            $users = $repo->findBy(
                [],
                [$orderBy => $request->query->get('ascending') ? 'ASC' : 'DESC'],
                $request->query->get('limit'),
                $request->query->get('limit') * ($request->query->get('page') -1)
            );
            $response = [
                'count' => $userCount,
                'data' => ArrayToJson::arrayToJson($users),
            ];
            return new JsonResponse($response);
        } else {
            // HTML
            return $this->render('AppBundle:User:overview.html.twig');
        }
    }

    /**
     * @Route("/admin/users")
     * @Method("POST")
     * @param  Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request)
    {
        $userManager = $this->get('fos_user.user_manager');
        $user = $userManager->createUser();

        $user->setFromJson(json_decode($request->getContent()));
        $userManager->updateUser($user);

        return new JsonResponse($user->getJson());
    }

    /**
     * @Route("/admin/users/{id}")
     * @Method("PUT")
     * @param  int    $id user id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUser(int $id, Request $request)
    {
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserBy(['id' => $id]);
        if ($user == null) {
            throw new NotFoundHttpException('There is no user with the requested id.');
        }

        $user->setFromJson(json_decode($request->getContent()));
        $userManager->updateUser($user);

        return new JsonResponse($user->getJson());
    }
}
