<?php

namespace AppBundle\Controller;

use DateTime;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class NewsEventController extends Controller
{

    /**
     * @Route("/news-events", name="news_events_get", methods={"GET"})
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getAll()
    {
        if ($this->isGranted('ROLE_EDITOR_VIEW')) {
            $newsEvents = $this->get('news_event_service')->getDateSorted();
        } else {
            $newsEvents = $this->get('news_event_service')->getPublicDateSorted();
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
            'AppBundle:NewsEvent:overview.html.twig',
            [
                'data' => $yearArray,
            ]
        );
    }

    /**
     * @Route("/news-events/edit", name="news_events_edit", methods={"GET"})
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $newsEvents = $this->get('news_event_service')->getAll();

        return $this->render(
            'AppBundle:NewsEvent:edit.html.twig',
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
     * @Route("/news-events/{id}", name="news_event_get", methods={"GET"})
     * @param int $id
     * @return Response
     */
    public function getSingle(int $id)
    {
        $newsEvent = $this->get('news_event_service')->getSingle($id);

        if (!$newsEvent) {
            $this->createNotFoundException('The news item or event with id "' . $id . '" could not be found.');
        }

        return $this->render(
            'AppBundle:NewsEvent:detail.html.twig',
            [
                'newsEvent' => $newsEvent,
            ]
        );
    }

    /**
     * @Route("/news-events", name="news_events_put", methods={"PUT"})
     * @param  Request $request
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function put(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $data = json_decode($request->getContent());
        $oldItems = $this->get('news_event_service')->getAll();
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
                ) {
                    $this->get('news_event_service')->update(
                        $item->id,
                        $this->get('security.token_storage')->getToken()->getUser()->getId(),
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
                $this->get('news_event_service')->insert(
                    $this->get('security.token_storage')->getToken()->getUser()->getId(),
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
                $this->get('news_event_service')->delete($oldItem['id']);
            }
        }

        return new JsonResponse(
            json_encode(
                $this->get('news_event_service')->getAll()
            )
        );
    }
}
