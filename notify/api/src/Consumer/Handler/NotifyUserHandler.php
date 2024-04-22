<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NotifyUserHandler extends AbstractLogHandler
{
    final public const EVENT = 'notify_user';

    public function __construct(private readonly MessageBusInterface $bus, private readonly ContactManager $contactManager)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $userId = $payload['user_id'];

        $contact = $this->contactManager->getContact($userId);
        if (!empty($payload['contact_info'])) {
            if (null !== $contact) {
                $this->contactManager->updateContact($contact, $payload['contact_info']);
            } else {
                $contact = $this->contactManager->createContact($userId, $payload['contact_info']);
            }
        }

        if (null === $contact) {
            $this->logger->error('Trying to notify user ID which is not existing in database. Maybe you forget to declare user info?');

            return;
        }

        if ($contact->getEmail()) {
            $this->eventProducer->publish(new EventMessage(SendEmailHandler::EVENT, [
                'email' => $contact->getEmail(),
                'template' => $payload['template'],
                'parameters' => $payload['parameters'] ?? [],
                'locale' => $contact->getLocale() ?? 'en',
            ]));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
