<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @param  Request $request
     */
    public function home(Request $request)
    {
        return $this->render(
            'AppBundle:Home:home.html.twig'
        );
    }

    /**
     * @Route("/about", name="about")
     * @param  Request $request
     */
    public function about(Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/help", name="help")
     * @param  Request $request
     */
    public function help(Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/contact", name="contact")
     * @param  Request $request
     */
    public function contact(Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Redirect user to the homepage after password reset
     * @Route("/profile", name="fos_user_profile_show")
     * @param  Request $request
     */
    public function showProfile(Request $request)
    {
        return $this->redirectToRoute('homepage');
    }
}
