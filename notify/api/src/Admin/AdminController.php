<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\TopicSubscriber;
use App\Topic\TopicManager;
use Arthem\Bundle\RabbitBundle\Controller\AdminReplayControllerTrait;
use Arthem\Bundle\RabbitBundle\Model\FailedEventManager;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    use AdminReplayControllerTrait;

    private TopicManager $topicManager;
    private EventProducer $eventProducer;
    private FailedEventManager $failedEventManager;

    public function __construct(
        TopicManager $topicManager,
        EventProducer $eventProducer,
        FailedEventManager $failedEventManager
    )
    {
        $this->topicManager = $topicManager;
        $this->eventProducer = $eventProducer;
        $this->failedEventManager = $failedEventManager;
    }

    protected function getFailedEventManager(): FailedEventManager
    {
        return $this->failedEventManager;
    }

    protected function getEventProducer(): EventProducer
    {
        return $this->eventProducer;
    }

    /**
     * @param TopicSubscriber $entity
     */
    public function persistTopicSubscriberEntity($entity)
    {
        $this->topicManager->addSubscriber($entity->getContact(), $entity->getTopic());
    }
}
