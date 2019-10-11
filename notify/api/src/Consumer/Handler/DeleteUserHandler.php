<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteUserHandler extends AbstractLogHandler
{
    const EVENT = 'delete_user';

    /**
     * @var ContactManager
     */
    private $contactManager;

    public function __construct(ContactManager $contactManager)
    {
        $this->contactManager = $contactManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $userId = $payload['user_id'];

        $contact = $this->contactManager->getContact($userId);
        if (null !== $contact) {
            $this->contactManager->deleteContact($contact);
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
