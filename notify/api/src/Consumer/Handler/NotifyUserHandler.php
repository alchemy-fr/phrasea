<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Contact\ContactManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class NotifyUserHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private ContactManager $contactManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyUser $message): void
    {
        $userId = $message->getUserId();

        $contact = $this->contactManager->getContact($userId);
        $contactInfo = $message->getContactInfo();
        if (!empty($contactInfo)) {
            if (null !== $contact) {
                $this->contactManager->updateContact($contact, $contactInfo);
            } else {
                $contact = $this->contactManager->createContact($userId, $contactInfo);
            }
        }

        if (null === $contact) {
            $this->logger->error('Trying to notify user ID which is not existing in database. Maybe you forget to declare user info?');

            return;
        }

        if ($contact->getEmail()) {
            $this->bus->dispatch(new SendEmail(
                $contact->getEmail(),
                $message->getTemplate(),
                $message->getParameters(),
                $contact->getLocale() ?? 'en',
            ));
        }
    }
}
