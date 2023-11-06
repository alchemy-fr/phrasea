<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class RegisterUserHandler extends AbstractLogHandler
{
    final public const EVENT = 'register_user';

    public function __construct(private readonly ContactManager $contactManager)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $userId = $payload['user_id'];
        $contactInfo = $payload['contact_info'];

        $contact = $this->contactManager->getContact($userId);
        if (null !== $contact) {
            $this->contactManager->updateContact($contact, $contactInfo);
        } else {
            $this->contactManager->createContact($userId, $contactInfo);
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
