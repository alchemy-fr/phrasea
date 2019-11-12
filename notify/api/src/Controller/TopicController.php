<?php

declare(strict_types=1);

namespace App\Controller;

use App\Topic\TopicManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/topic")
 */
class TopicController extends AbstractController
{
    /**
     * @Route("/{topic}/subscribers/{id}", methods={"POST"})
     */
    public function addSubscriber(string $topic, string $id, TopicManager $topicManager)
    {
        $contact = $topicManager->getContactById($id);
        $topicManager->addSubscriber($contact, $topic);

        return new JsonResponse(true);
    }

    /**
     * @Route("/{topic}/subscribers/{id}", methods={"DELETE"})
     */
    public function removeSubscriber(string $topic, string $id, TopicManager $topicManager)
    {
        $contact = $topicManager->getContactById($id);
        $topicManager->removeSubscriber($contact, $topic);

        return new JsonResponse(true);
    }
}
