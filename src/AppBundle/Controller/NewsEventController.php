<?php

namespace AppBundle\Controller;

use DateTime;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class NewsEventController extends Controller
{
    /**
     * @Route("/news_events/edit", name="news_events_edit")
     * @Method("GET")
     * @param  Request $request
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $newsEvents = $this->get('news_event_service')->getAll();
        return $this->render(
            'AppBundle:NewsEvent:edit.html.twig',
            [
                'urls' => json_encode([
                    'news_events_put' => $this->generateUrl('news_events_put'),
                    'homepage' => $this->generateUrl('homepage'),
                ]),
                'data' => json_encode($newsEvents),
            ]
        );
    }

    /**
     * @Route("/news-events", name="news_events_put")
     * @Method("PUT")
     * @param  Request $request
     */
    public function put(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (explode(',', $request->headers->get('Accept'))[0] != 'application/json') {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }

        $data = json_decode($request->getContent());
        $oldItems = $this->get('news_event_service')->getAll();
        $oldItemIndeces = [];

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
                    property_exists($newsEvent, 'id') && !is_numeric($newsEvent->id)
                )
                || !property_exists($newsEvent, 'title')
                || empty($newsEvent->title)
                || !is_string($newsEvent->title)
                || !property_exists($newsEvent, 'url')
                || empty($newsEvent->url)
                || !is_string($newsEvent->url)
                || (
                    property_exists($newsEvent, 'date')
                    && !empty($newsEvent->date)
                    && (!is_string($newsEvent->date) || !(DateTime::createFromFormat('Y-m-d', $newsEvent->date)))
                )
                || (
                    property_exists($newsEvent, 'public')
                    && !is_bool($newsEvent->public)
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
            if (!property_exists($newsEvent, 'date')) {
                $newsEvent->date = '';
            }
            if (!property_exists($newsEvent, 'public')) {
                $newsEvent->public = false;
            }
        }
        if (count($ids) > 0) {
            foreach ($ids as $id) {
                foreach ($oldItems as $oldIndex => $oldItem) {
                    if ($oldItem['id'] == $id) {
                        $oldItemIndeces[$id] = $oldIndex;
                        break;
                    }
                }
                if (!isset($oldItemIndeces[$id])) {
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

        foreach ($data as $order => $newsItem) {
            if (property_exists($newsItem, 'id')) {
                $oldItem = $oldItems[$oldItemIndeces[$newsItem->id]];
                if ($oldItem['title'] != $newsItem->title
                    || $oldItem['url'] != $newsItem->url
                    || $oldItem['date'] != $newsItem->date
                    || $oldItem['public'] != $newsItem->public
                    || $oldItem['order'] != $order
                ) {
                    $this->get('news_event_service')->update(
                        $newsItem->id,
                        $this->get('security.token_storage')->getToken()->getUser()->getId(),
                        $newsItem->title,
                        $newsItem->url,
                        $newsItem->date,
                        $newsItem->public ?? false,
                        $order
                    );
                }
            } else {
                $this->get('news_event_service')->insert(
                    $this->get('security.token_storage')->getToken()->getUser()->getId(),
                    $newsItem->title,
                    $newsItem->url,
                    $newsItem->date,
                    $newsItem->public ?? false,
                    $order
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
