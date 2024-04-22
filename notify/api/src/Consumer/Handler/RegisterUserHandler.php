<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterUserHandler
{
    public function __construct(private ContactManager $contactManager)
    {
    }

    public function __invoke(RegisterUser $message): void
    {
        $contact = $this->contactManager->getContact($message->getUserId());
        if (null !== $contact) {
            $this->contactManager->updateContact($contact, $message->getContactInfo());
        } else {
            $this->contactManager->createContact($message->getUserId(), $message->getContactInfo());
        }
    }
}
