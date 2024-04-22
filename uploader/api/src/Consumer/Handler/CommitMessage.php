<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class CommitMessage
{
    public function __construct(
        private string $targetId,
        private string $userId,
        private array $files,
        private array $form,
        private ?string $notifyEmail = null,
        private ?string $locale = null,
        private array $options = [],
    ) {
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getForm(): array
    {
        return $this->form;
    }

    public function getNotifyEmail(): ?string
    {
        return $this->notifyEmail;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
