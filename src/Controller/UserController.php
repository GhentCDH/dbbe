<?php

namespace App\Controller;

use DateTime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Unirest\Request as RestRequest;

use App\Entity\User;
use App\Exceptions\HttpException;
use App\Exceptions\UserAlreadyExistsException;
use App\Utils\ArrayToJson;

class UserController extends AbstractController
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
     * @Route("/users", name="users_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsers(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->throwErrorIfNotJson($request);


        if (in_array('application/json', explode(', ', $request->headers->get('Accept')))) {
            // JSON
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository(User::class);
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
     * @Route("/users/edit", name="users_edit", methods={"GET"})
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render(
            'User/edit.html.twig',
            [
                'urls' => json_encode([
                    'users_get' => $this->generateUrl('users_get'),
                    'user_post' => $this->generateUrl('user_post'),
                    'user_put' => $this->generateUrl('user_put', ['id' => 'user_id']),
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('app_login') : $this->generateUrl('saml_login'),
                ]),
            ]
        );
    }

    /**
     * @Route("/users", name="user_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     * @throws HttpException|GuzzleHttp\Exception\GuzzleException
     */
    public function post(Request $request)
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

        if (!isset($input['roles']) || !is_array($input['roles'])
        ) {
            throw new BadRequestHttpException('Invalid roles.');
        }

        foreach ($input['roles'] as $role) {
            if (!in_array($role, self::ALLOWED_ROLES)) {
                throw new BadRequestHttpException('Invalid role.');
            }
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(User::class);

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
            try {
                // create welkom account
                $this->createWelkomAccount($input['username']);
                // send invite mail
                $this->sendWelkomMail($input['username']);
            } catch (UserAlreadyExistsException $e) {
                // If the user already exists, no e-mail will be sent.
            }
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse($user->getJson());
    }

    /**
     * @Route("/users/{id}", name="user_put", methods={"PUT"})
     * @param  int    $id user id
     * @param Request $request
     * @return JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->throwErrorIfNotJson($request);

        $input = json_decode($request->getContent(), true);

        if (!isset($input['roles']) || !is_array($input['roles'])
        ) {
            throw new BadRequestHttpException('Invalid roles.');
        }

        foreach ($input['roles'] as $role) {
            if (!in_array($role, self::ALLOWED_ROLES)) {
                throw new BadRequestHttpException('Invalid role.');
            }
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(User::class);

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
    private function throwErrorIfNotJson(Request $request): void
    {
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @param string $username
     * @throws HttpException
     * @throws UserAlreadyExistsException
     */
    private function createWelkomAccount(string $username): void
    {
        RestRequest::auth($this->getParameter('app.saml_username'), $this->getParameter('app.saml_password'));
        $response = RestRequest::post(
            $this->getParameter('app.saml_create'),
            null,
            RestRequest\Body::json([
                'genUid' => $username,
                'accountUUID' => Uuid::v4()->toRfc4122(),
                'userClass' => 'dbbe',
            ])
        );

        if ($response->code !== 200) {
            throw new HttpException('Unexpected response code: ' . $response->code);
        }
    }

    /**
     * @param string $username
     * @throws HttpException
     */
    private function sendWelkomMail(string $username): void
    {
        RestRequest::auth($this->getParameter('app.saml_username'), $this->getParameter('app.saml_password'));
        $response = RestRequest::post(
            $this->getParameter('app.saml_mail'),
            null,
            RestRequest\Body::json([
                'username' => $username,
                'language' => 'en',
                'personalizedEmailTemplate' => 'dbbe',
                'returnUrl' => $this->generateUrl('saml_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ])
        );

        if ($response->code !== 200) {
            throw new HttpException('Unexpected response code: ' . $response->code);
        }
    }
}
