<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteUserHandler
{
    public function __construct(private ContactManager $contactManager)
    {
    }

    public function __invoke(DeleteUser $message): void
    {
        $contact = $this->contactManager->getContact($message->getId());
        if (null !== $contact) {
            $this->contactManager->deleteContact($contact);
        }
    }
}
