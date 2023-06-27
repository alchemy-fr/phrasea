<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Topic\TopicManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NotifyTopicHandler extends AbstractLogHandler
{
    public const EVENT = 'notify_topic';

    /**
     * @var EventProducer
     */
    private $eventProducer;
    /**
     * @var TopicManager
     */
    private $topicManager;

    public function __construct(EventProducer $eventProducer, TopicManager $topicManager)
    {
        $this->eventProducer = $eventProducer;
        $this->topicManager = $topicManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $topic = $payload['topic'];
        $template = $payload['template'];
        $parameters = $payload['parameters'];

        $contacts = $this->topicManager->getSubscriptions($topic);
        foreach ($contacts as $contact) {
            $this->eventProducer->publish(new EventMessage(NotifyUserHandler::EVENT, [
                'user_id' => $contact->getContact()->getUserId(),
                'template' => $template,
                'parameters' => $parameters,
            ]));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
