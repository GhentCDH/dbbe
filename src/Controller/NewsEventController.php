<?php

namespace App\Controller;

use DateTime;

use Doctrine\DBAL\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\DatabaseService\NewsEventService;
use App\Security\Roles;

class NewsEventController extends AbstractController
{
    /**
     * @param NewsEventService $newsEventService
     * @return Response
     * @throws DBALException
     */

#[Route(path: '/news-events', name: 'news_events_get',  methods: ['GET'])]
public function getAll(NewsEventService $newsEventService): Response
    {
        if ($this->isGranted(Roles::ROLE_EDITOR_VIEW)) {
            $newsEvents = $newsEventService->getDateSorted();
        } else {
            $newsEvents = $newsEventService->getPublicDateSorted();
        }

        // Categorize per year
        $yearArray = [];
        foreach ($newsEvents as $newsEvent) {
            $year = substr($newsEvent['date'], 0, 4);
            if (!isset($yearArray[$year])) {
                $yearArray[$year] = [];
            }
            $yearArray[$year][] = $newsEvent;
        }

        return $this->render(
            'NewsEvent/overview.html.twig',
            [
                'data' => $yearArray,
            ]
        );
    }

    /**
     * @param NewsEventService $newsEventService
     * @return Response
     * @throws DBALException
     */
    #[Route(path: '/news-events/edit', name: 'news_events_edit',  methods: ['GET'])]
    public function edit(NewsEventService $newsEventService): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_ADMIN);

        $newsEvents = $newsEventService->getAll();

        return $this->render(
            'NewsEvent/edit.html.twig',
            [
                'urls' => json_encode([
                    'news_events_get' => $this->generateUrl('news_events_get'),
                    'news_event_get' => $this->generateUrl('news_event_get', ['id' => 'news_event_id']),
                    'news_events_put' => $this->generateUrl('news_events_put'),
                ]),
                'data' => json_encode($newsEvents),
            ]
        );
    }

    /**
     * @param int $id
     * @param NewsEventService $newsEventService
     * @return Response
     * @throws DBALException
     */
    #[Route(path: '/news-events/{id}', name: 'news_event_get',  methods: ['GET'])]
    public function getSingle(int $id, NewsEventService $newsEventService): Response
    {
        $newsEvent = $newsEventService->getSingle($id);

        if (!$newsEvent) {
            $this->createNotFoundException('The news item or event with id "' . $id . '" could not be found.');
        }

        return $this->render(
            'NewsEvent/detail.html.twig',
            [
                'newsEvent' => $newsEvent,
            ]
        );
    }

    /**
     * @param Request $request
     * @param NewsEventService $newsEventService
     * @return JsonResponse
     * @throws DBALException
     */
    #[Route(path: '/news-events', name: 'news_events_put',  methods: ['PUT'])]
    public function put(Request $request, NewsEventService $newsEventService): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_ADMIN);
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $data = json_decode($request->getContent());
        $oldItems = $newsEventService->getAll();
        $oldItemIndices = [];

        // Sanitation
        if (!is_array($data)) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Incorrect data.'
                    ]
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
        $ids = [];
        foreach ($data as $newsEvent) {
            if ((
                    property_exists($newsEvent, 'id')
                    && !is_numeric($newsEvent->id)
                )
                || !property_exists($newsEvent, 'title')
                || empty($newsEvent->title)
                || !is_string($newsEvent->title)
                || !property_exists($newsEvent, 'date')
                || empty($newsEvent->date)
                || !is_string($newsEvent->date)
                || !(DateTime::createFromFormat('Y-m-d', $newsEvent->date))
                || (
                    property_exists($newsEvent, 'public')
                    && !is_bool($newsEvent->public)
                )
                || (
                    property_exists($newsEvent, 'abstract')
                    && !empty($newsEvent->abstract)
                    && !is_string($newsEvent->abstract)
                )
                // only url or full text can be used
                || (
                    (property_exists($newsEvent, 'url') && !empty($newsEvent->url))
                    && (property_exists($newsEvent, 'text') && !empty($newsEvent->text))
                )
                || (
                    property_exists($newsEvent, 'url')
                    && !empty($newsEvent->url)
                    && !is_string($newsEvent->url)
                )
                || (
                    property_exists($newsEvent, 'text')
                    && !empty($newsEvent->text)
                    && !is_string($newsEvent->text)
                )
            ) {
                return new JsonResponse(
                    [
                        'error' => [
                            'code' => Response::HTTP_BAD_REQUEST,
                            'message' => 'Incorrect news item or event data.'
                        ]
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }
            if (property_exists($newsEvent, 'id')) {
                $ids[] = $newsEvent->id;
            }
            if (!property_exists($newsEvent, 'public')) {
                $newsEvent->public = true;
            }
            if (!property_exists($newsEvent, 'abstract')) {
                $newsEvent->abstract = null;
            }
            if (!property_exists($newsEvent, 'url')) {
                $newsEvent->url = null;
            }
            if (!property_exists($newsEvent, 'text')) {
                $newsEvent->text = null;
            }
        }
        if (count($ids) > 0) {
            foreach ($ids as $id) {
                foreach ($oldItems as $oldIndex => $oldItem) {
                    if ($oldItem['id'] == $id) {
                        $oldItemIndices[$id] = $oldIndex;
                        break;
                    }
                }
                if (!isset($oldItemIndices[$id])) {
                    return new JsonResponse(
                        [
                            'error' => [
                                'code' => Response::HTTP_BAD_REQUEST,
                                'message' => 'News item or event with id "' . $id . '" could not be found.'
                            ]
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
        }

        foreach ($data as $order => $item) {
            if (property_exists($item, 'id')) {
                $oldItem = $oldItems[$oldItemIndices[$item->id]];
                if ($oldItem['title'] != $item->title
                    || $oldItem['abstract'] != $item->abstract
                    || $oldItem['url'] != $item->url
                    || $oldItem['date'] != $item->date
                    || $oldItem['public'] != $item->public
                    || $oldItem['order'] != $order
                    || $oldItem['text'] != $item->text
                ) {
                    $newsEventService->update(
                        $item->id,
                        $this->get('security.token_storage')->getToken()->getUser()->getEmail(),
                        $item->title,
                        $item->url,
                        $item->date,
                        $item->public ?? false,
                        $order,
                        $item->abstract,
                        $item->text
                    );
                }
            } else {
                $newsEventService->insert(
                    $this->get('security.token_storage')->getToken()->getUser()->getEmail(),
                    $item->title,
                    $item->url,
                    $item->date,
                    $item->public ?? false,
                    $order,
                    $item->abstract,
                    $item->text
                );
            }
        }

        foreach ($oldItems as $oldItem) {
            if (!in_array($oldItem['id'], $ids)) {
                $newsEventService->delete($oldItem['id']);
            }
        }

        return new JsonResponse(
            json_encode(
                $newsEventService->getAll()
            )
        );
    }
}
