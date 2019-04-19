<?php

namespace AppBundle\Controller;

use AppBundle\Exceptions\CurlException;
use DateTime;

use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Entity\User;
use AppBundle\Utils\ArrayToJson;

class UserController extends Controller
{
    const ALLOWED_ROLES = [
        'ROLE_USER',
        'ROLE_VIEW_INTERNAL',
        'ROLE_EDITOR_VIEW',
        'ROLE_EDITOR',
        'ROLE_JULIE',
        'ROLE_ADMIN',
        'ROLE_SUPER_ADMIN'
    ];

    /**
     * @Route("/users", name="users_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getUsers(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->throwErrorIfNotJson($request);


        if (in_array('application/json', explode(', ', $request->headers->get('Accept')))) {
            // JSON
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository('AppBundle:User');
            $userCount = count($repo->findAll());
            switch ($request->query->get('orderBy')) {
                case 'username':
                    $orderBy = 'username';
                    break;
                case 'created':
                    $orderBy = 'created';
                    break;
                case 'modified':
                    $orderBy = 'modified';
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
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/users/edit", name="users_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editUsers(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render(
            'AppBundle:User:edit.html.twig',
            [
                'urls' => json_encode([
                    'users_get' => $this->generateUrl('users_get'),
                    'user_post' => $this->generateUrl('user_post'),
                    'user_put' => $this->generateUrl('user_put', ['id' => 'user_id']),
                    'login' => $this->generateUrl('saml_login'),
                ]),
            ]
        );
    }

    /**
     * @Route("/users", name="user_post")
     * @Method("POST")
     * @param  Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->throwErrorIfNotJson($request);

        $input = json_decode($request->getContent(), true);

        if (!isset($input['username'])
            || empty($input['username'])
            || !is_string($input['username'])
            || strlen($input['username']) > 255
            || preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $input['username']) == 0
        ) {
            throw new BadRequestHttpException('Invalid username.');
        }

        if (!isset($input['roles'])
            || !is_array($input['roles'])
        ) {
            throw new BadRequestHttpException('Invalid roles.');
        }

        foreach ($input['roles'] as $role) {
            if (!in_array($role, self::ALLOWED_ROLES)) {
                throw new BadRequestHttpException('Invalid role.');
            }
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:User');

        $user = $repo->findOneBy(['username' => $input['username']]);
        if ($user != null) {
            throw new BadRequestHttpException('There already is a user with the requested username.');
        }

        $user = new User();
        $user->setUsername($input['username']);
        $user->setRoles($input['roles']);

        $match = preg_match('/@ugent[.]be$/i', $input['username']);
        if ($match === false) {
            throw new BadRequestHttpException('Error while checking the username.');
        }

        // non-ugent user
        if ($match === 0) {
            // create welkom account
            $this->createWelkomAccount($input['username']);
            // send invite mail
            $this->sendWelkomMail($input['username']);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse($user->getJson());
    }

    /**
     * @Route("/users/{id}", name="user_put")
     * @Method("PUT")
     * @param  int    $id user id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUser(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->throwErrorIfNotJson($request);

        $input = json_decode($request->getContent(), true);

        if (!isset($input['roles'])
            || !is_array($input['roles'])
        ) {
            throw new BadRequestHttpException('Invalid roles.');
        }

        foreach ($input['roles'] as $role) {
            if (!in_array($role, self::ALLOWED_ROLES)) {
                throw new BadRequestHttpException('Invalid role.');
            }
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('AppBundle:User');

        $user = $repo->findOneBy(['id' => $id]);
        if ($user == null) {
            throw new NotFoundHttpException('There is no user with the requested id.');
        }

        $user->setModified(new DateTime());
        $user->setRoles($input['roles']);

        $em->persist($user);
        $em->flush();

        return new JsonResponse($user->getJson());
    }

    /**
     * Throws a BadRequestHttpException if the accept header is not set to application/json
     * @param Request $request
     */
    private function throwErrorIfNotJson($request): void
    {
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    private function createWelkomAccount(string $username): void
    {
        $dataString = json_encode(
            [
                'genUid' => $username,
                'accountUUID' => Uuid::uuid4()->toString(),
                'userClass' => 'casonly',
            ]
        );
        $ch = curl_init($this->getParameter('saml_create'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($dataString),
            ]
        );
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt(
            $ch,
            CURLOPT_USERPWD,
            $this->getParameter('saml_username') . ':' . $this->getParameter('saml_password')
        );
        curl_exec($ch);
        if (curl_errno($ch) !== 0) {
            throw new CurlException(curl_error($ch));
        }
        if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new CurlException('Unexpected response code: ' . curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        }

        curl_close($ch);
    }

    private function sendWelkomMail(string $username): void
    {
        $dataString = json_encode(
            [
                'username' => $username,
                'language' => 'en',
                'personalizedEmailTemplate' => 'dbbe',
            ]
        );
        $ch = curl_init($this->getParameter('saml_mail'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($dataString),
            ]
        );
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt(
            $ch,
            CURLOPT_USERPWD,
            $this->getParameter('saml_username') . ':' . $this->getParameter('saml_password')
        );
        curl_exec($ch);
        if (curl_errno($ch) !== 0) {
            throw new CurlException(curl_error($ch));
        }
        if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new CurlException('Unexpected response code: ' . curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        }

        curl_close($ch);
    }
}
