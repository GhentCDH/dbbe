<?php

namespace App\Controller;

use App\DatabaseService\JulieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\Security\Roles;

class JulieController extends AbstractController
{
    /**
     * @Route("/julie", name="julie")
     * @Route("/julie/{route}", name="julie_pages", requirements={"route"="^.+"})
     * @return Response
     */
    public function julie()
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);

        return $this->render('Julie/index.html.twig');
    }

    /**
     * @Route("/originalpoem/{id}", name="originalpoem_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function getOriginalPoem(int $id, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
        $this->throwErrorIfNotJson($request);

        $originalpoem = $julieService->getOriginalPoem($id);

        if (!$originalpoem) {
            throw $this->createNotFoundException('The requested original poem does not exist');
        }

        return new JsonResponse($originalpoem);
    }

    /**
     * @Route("/substringannotation/{occurrenceId}", name="substringannotation_get", methods={"GET"})
     * @param int $occurrenceId
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function getSubstringAnnotation(int $occurrenceId, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
        $this->throwErrorIfNotJson($request);

        $annotations = $julieService->getSubstringAnnotation($occurrenceId);

        return new JsonResponse($annotations);
    }

    /**
     * @Route("/substringannotation/{occurrenceId}", name="substringannotation_post", methods={"POST"})
     * @param int $occurrenceId
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function postSubstringAnnotation(int $occurrenceId, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
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

        $julieService->postSubstringAnnotation($occurrenceId, $content);

        return new JsonResponse('Done');
    }

    /**
     * @Route("/substringannotation/{annotationId}", name="substringannotation_delete", methods={"DELETE"})
     * @param int $annotationId
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function deleteSubstringAnnotation(int $annotationId, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
        $this->throwErrorIfNotJson($request);

        $julieService->deleteSubstringAnnotation($annotationId);

        return new JsonResponse('Done');
    }

    /**
     * @Route("/poemannotation/{occurrenceId}", name="poemannotation_get", methods={"GET"})
     * @param int $occurrenceId
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function getPoemAnnotation(int $occurrenceId, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
        $this->throwErrorIfNotJson($request);

        $annotation = $julieService->getPoemAnnotation($occurrenceId);

        if (!$annotation) {
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($annotation);
    }

    /**
     * @Route("/poemannotation/{occurrenceId}", name="poemannotation_put", methods={"PUT"})
     * @param int $occurrenceId
     * @param Request $request
     * @param JulieService $julieService
     * @return JsonResponse
     */
    public function putPoemAnnotation(int $occurrenceId, Request $request, JulieService $julieService)
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_JULIE);
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent(), true);
        if (!isset($content['key'])
            || !is_string($content['key'])
            || $content['key'] !== 'prosodycorrect'
            || ((!isset($content['value']) || !is_string($content['value'])) && !is_null($content['value']))
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }

        $julieService->upsertPoemAnnotationProsodyCorrect($occurrenceId, $content['value']);

        return new JsonResponse('Done');
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
}
