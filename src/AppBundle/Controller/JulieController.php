<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JulieController extends Controller
{
    /**
     * @Route("/julie", name="julie")
     * @Route("/julie/{route}", name="julie_pages", requirements={"route"="^.+"})
     * @return Response
     */
    public function julie()
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');

        return $this->render('AppBundle:Julie:index.html.twig');
    }

    /**
     * @Route("/originalpoem/{id}", name="originalpoem_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getOriginalPoem(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');

        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $originalpoem = $this->get('julie_service')->getOriginalPoem($id);

        if (!$originalpoem) {
            throw $this->createNotFoundException('The requested original poem does not exist');
        }

        return new JsonResponse($originalpoem);
    }

    /**
     * @Route("/substringannotation/{id}", name="substringannotation_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getSubstringAnnotation(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');

        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $annotations = $this->get('julie_service')->getSubstringAnnotation($id);

        return new JsonResponse($annotations);
    }

    /**
     * @Route("/substringannotation/{id}", name="substringannotation_post")
     * @Method("POST")
     * @param  int    $id
     * @param Request $request
     */
    public function postSubstringAnnotation(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');

        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $content = json_decode($request->getContent(), true);
        if (!isset($content['startindex'])
            || !is_int($content['startindex'])
            || !isset($content['endindex'])
            || !is_int($content['endindex'])
            || !isset($content['substring'])
            || !is_string($content['substring'])
            || !isset($content['key'])
            || !is_string($content['key'])
            || !isset($content['value'])
            || !is_string($content['value'])
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }

        $this->get('julie_service')->postSubstringAnnotation($id, $content);

        return new JsonResponse('Done');
    }

    /**
     * @Route("/poemannotation/{id}", name="poemannotation_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getPoemAnnotation(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');

        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $annotation = $this->get('julie_service')->getPoemAnnotation($id);

        if (!$annotation) {
            throw $this->createNotFoundException('The requested poem annotation does not exist');
        }

        return new JsonResponse($annotation);
    }
}
