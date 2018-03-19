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
        $content = json_decode($request->getContent());
        if (!property_exists($content, 'email')
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

        


        throw new \Exception('Not implemented');
    }
}
