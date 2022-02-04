<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Unirest\Request as RestRequest;

use App\DatabaseService\FeedbackService;

class FeedbackController extends AbstractController
{
    /**
     * @Route("/feedback", name="feedback", methods={"POST"})
     * @param Request $request
     * @param FeedbackService $feedbackService
     * @return JsonResponse
     */
    public function postFeedback(Request $request, FeedbackService $feedbackService, ValidatorInterface $validator, MailerInterface $mailer)
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
            || count($validator->validate($content->email, new EmailConstraint())) > 0
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
                'secret' => $this->getParameter('app.secretKey'),
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
        $feedbackService->insertFeedback($content->url, $content->email, $content->message);

        // send email
        $email = (new Email())
            ->from('dbbe@ugent.be')
            ->to($content->email)
            ->cc('dbbe@ugent.be')
            ->subject('Your feedback message to DBBE')
            ->text(
                $this->renderView(
                    'Feedback/email.txt.twig',
                    [
                        'url' => $content->url,
                        'email' => $content->email,
                        'message' => $content->message,
                    ]
                )
            );
        $mailer->send($email);

        // send success response
        return new JsonResponse(['success']);
    }
}
