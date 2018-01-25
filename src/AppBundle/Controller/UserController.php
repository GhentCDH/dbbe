<?php

namespace AppBundle\Controller;

use stdClass;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Entity\User;

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
            $responseUsers = [];
            foreach ($users as $user) {
                $responseUsers[] = self::transformUser($user);
            }
            $response = [
                'count' => $userCount,
                'data' => $responseUsers,
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

        $user = self::setUserData($user, json_decode($request->getContent()));
        $userManager->updateUser($user);

        return new JsonResponse(self::transformUser($user));
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
            throw $this->createNotFoundException('There is no user with the requested id.');
        }

        $user = self::setUserData($user, json_decode($request->getContent()));
        $userManager->updateUser($user);

        return new JsonResponse(self::transformUser($user));
    }

    /**
     * @Route("/admin/users/{id}")
     * @Method("DELETE")
     * @param  int    $id user id
     * @return Response
     */
    public function deleteUser(int $id)
    {
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserBy(['id' => $id]);
        if ($user == null) {
            throw $this->createNotFoundException('There is no user with the requested id.');
        }

        $userManager->deleteUser($user);

        return new Response(null, 204);
    }

    /**
     * Set the user data from an object
     * @param User  $user
     * @param stdClass $data
     * @return User
     */
    private static function setUserData(User $user, stdClass $data)
    {
        // Generate a random password for new users
        if ($user->getPassword() == null) {
            $user->setPassword(base64_encode(random_bytes(16)));
        }

        $user->setUsername($data->username);
        $user->setEmail($data->email);
        $user->setFullName($data->{'full name'});

        $addRoles = array_diff($data->roles, $user->getRoles());
        $removeRoles = array_diff($user->getRoles(), $data->roles);
        foreach ($addRoles as $role) {
            $user->addRole($role);
        }
        foreach ($removeRoles as $role) {
            $user->removeRole($role);
        }

        $user->setEnabled($data->status);
        $user->setModified(new \DateTime());

        return $user;
    }

    /**
     * Transform a user object to an array, so it can be converted to JSON
     * @param  User   $user
     * @return array
     */
    private static function transformUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsernameCanonical(),
            'email' => $user->getEmailCanonical(),
            'full name' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'status' => $user->isEnabled(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
            'modified' => $user->getModified()->format('Y-m-d H:i:s'),
            // last login is not required
            'last login' => $user->getLastLogin() ? $user->getLastLogin()->format('Y-m-d H:i:s') : null,
        ];
    }
}
