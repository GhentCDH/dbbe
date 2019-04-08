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
     * @param int     $id
     * @param Request $request
     */
    public function getOriginalPoem(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

        $originalpoem = $this->get('julie_service')->getOriginalPoem($id);

        if (!$originalpoem) {
            throw $this->createNotFoundException('The requested original poem does not exist');
        }

        return new JsonResponse($originalpoem);
    }

    /**
     * @Route("/substringannotation/{occurrenceId}", name="substringannotation_get")
     * @Method("GET")
     * @param int     $occurrenceId
     * @param Request $request
     */
    public function getSubstringAnnotation(int $occurrenceId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

        $annotations = $this->get('julie_service')->getSubstringAnnotation($occurrenceId);

        return new JsonResponse($annotations);
    }

    /**
     * @Route("/substringannotation/{occurrenceId}", name="substringannotation_post")
     * @Method("POST")
     * @param int     $occurrenceId
     * @param Request $request
     */
    public function postSubstringAnnotation(int $occurrenceId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

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

        $this->get('julie_service')->postSubstringAnnotation($occurrenceId, $content);

        return new JsonResponse('Done');
    }

    /**
     * @Route("/substringannotation/{annotationId}", name="substringannotation_delete")
     * @Method("DELETE")
     * @param int     $annotationId
     * @param Request $request
     */
    public function deleteSubstringAnnotation(int $annotationId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

        $this->get('julie_service')->deleteSubstringAnnotation($annotationId);

        return new JsonResponse('Done');
    }

    /**
     * @Route("/poemannotation/{occurrenceId}", name="poemannotation_get")
     * @Method("GET")
     * @param int     $occurrenceId
     * @param Request $request
     */
    public function getPoemAnnotation(int $occurrenceId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

        $annotation = $this->get('julie_service')->getPoemAnnotation($occurrenceId);

        if (!$annotation) {
            return new JsonResponse(null,204);
        }

        return new JsonResponse($annotation);
    }

    /**
     * @Route("/poemannotation/{occurrenceId}", name="poemannotation_put")
     * @Method("PUT")
     * @param int     $occurrenceId
     * @param Request $request
     */
    public function putPoemAnnotation(int $occurrenceId, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_JULIE');
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent(), true);
        if (!isset($content['key'])
            || !is_string($content['key'])
            || $content['key'] !== 'prosodycorrect'
            || ((!isset($content['value']) || !is_string($content['value'])) && !is_null($content['value']))
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }

        $this->get('julie_service')->upsertPoemAnnotationProsodyCorrect($occurrenceId, $content['value']);

        return new JsonResponse('Done');
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
}
