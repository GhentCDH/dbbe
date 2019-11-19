<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Validator\Constraints\Email as EmailConstaint;
use Symfony\Component\HttpFoundation\Request;
use Unirest\Request as RestRequest;

class FeedbackController extends Controller
{
    /**
     * @Route("/feedback", name="feedback")
     * @param Request $request
     * @Method("POST")
     */
    public function postFeedback(Request $request)
    {
        // sanitize input
        $content = json_decode($request->getContent());
        if (!property_exists($content, 'url')
            || !is_string($content->url)
            || empty($content->url)
            || strlen($content->url) < 1
            || strlen($content->url) > 3999
            || !property_exists($content, 'email')
            || !is_string($content->email)
            || empty($content->email)
            || strlen($content->email) < 1
            || strlen($content->email) > 3999
            || !empty($this->get('validator')->validate($content->email, new EmailConstaint())->count() != 0)
            || !property_exists($content, 'message')
            || !is_string($content->message)
            || empty($content->message)
            || strlen($content->message) < 1
            || strlen($content->message) > 3999
            || !property_exists($content, 'recaptcha')
            || !is_string($content->recaptcha)
            || empty($content->recaptcha)
        ) {
            return new JsonResponse(['error' => ['code' => 400, 'message' => 'Invalid request']], 400);
        }

        // Verify captcha
        $response = RestRequest::post(
            'https://www.google.com/recaptcha/api/siteverify',
            null,
            [
                'secret' => $this->getParameter('secretKey'),
                'response' => $content->recaptcha,
            ]
        );
        if (!property_exists($response, 'body')
            || !property_exists($response->body, 'success')
            || !$response->body->success
        ) {
            return new JsonResponse(['error' => ['code' => 400, 'message' => 'Invalid captcha']], 400);
        }

        // save message in database
        $this->get('feedback_service')->insertFeedback($content->url, $content->email, $content->message);

        // send email
        $message = (new \Swift_Message('Feedback message from DBBE'))
            ->setFrom('dbbe@ugent.be')
            ->setTo($content->email)
            ->setCC('dbbe@ugent.be')
            ->setBody(
                $this->renderView(
                    'AppBundle:Feedback:email.txt.twig',
                    [
                        'url' => $content->url,
                        'email' => $content->email,
                        'message' => $content->message,
                    ]
                ),
                'text/plain'
            );
        $this->get('mailer')->send($message);

        // send success response
        return new JsonResponse(['success']);
    }
}
