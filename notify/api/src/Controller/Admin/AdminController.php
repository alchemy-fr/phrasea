<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TopicSubscriber;
use App\Topic\TopicManager;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    /**
     * @var TopicManager
     */
    private $topicManager;

    public function __construct(TopicManager $topicManager)
    {
        $this->topicManager = $topicManager;
    }

    /**
     * @param TopicSubscriber $entity
     */
    public function persistTopicSubscriberEntity($entity)
    {
        $this->topicManager->addSubscriber($entity->getContact(), $entity->getTopic());
    }
}
