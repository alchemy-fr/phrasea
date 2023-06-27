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

    public function __construct(private readonly TopicManager $topicManager, private readonly EventProducer $eventProducer, private readonly FailedEventManager $failedEventManager)
    {
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
