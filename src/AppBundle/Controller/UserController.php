<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    /**
     * @Route("/admin/users")
     * @param Request $request httpfoundation request
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
            $responseUsers = [];
            foreach ($users as $user) {
                $responseUsers[] = [
                    'id' => $user->getId(),
                    'username' => $user->getUsernameCanonical(),
                    'email' => $user->getEmailCanonical(),
                    'full name' => $user->getFullName(),
                    'roles' => $user->getRoles(),
                    'status' => $user->isEnabled(),
                    'created' => $user->getCreated()->format('Y-m-d H:i:s'),
                    'modified' => $user->getModified()->format('Y-m-d H:i:s'),
                    'last login' => $user->getLastLogin() ? $user->getLastLogin()->format('Y-m-d H:i:s') : null,
                ];
            }
            $response = [
                'count' => $userCount,
                'data' => $responseUsers,
            ];
            return new JsonResponse($response);
        } else {
            // HTML
            return $this->render('AppBundle:Users:users.html.twig');
        }
    }

    /**
     * @Route("/admin/users/{id}/edit")
     * @param  int    $id user id
     */
    public function editUser(int $id)
    {
        $userManager = $this->get('fos_user.user_manager');
        $user = $userManager->findUserBy(['id' => $id]);

        return $this->render('AppBundle:Users:edit.html.twig', ['user' => $user ]);
    }
}
