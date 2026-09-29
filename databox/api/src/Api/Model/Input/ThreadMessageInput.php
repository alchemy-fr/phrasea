<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ThreadMessageInput
{
    /**
     * May be blank when the message has attachments (files only).
     */
    public ?string $content = null;

    public ?array $attachments = null;

    public ?string $threadKey = null;

    public ?string $threadId = null;

    #[Assert\Callback]
    public function validateThreadKeyOrThreadId(): void
    {
        if (null === $this->threadKey && null === $this->threadId) {
            throw new \InvalidArgumentException('You must provide either a "threadKey" or a "threadId"');
        }
    }

    #[Assert\Callback]
    public function validateContentOrAttachments(ExecutionContextInterface $context): void
    {
        if ('' === trim((string) $this->content) && empty($this->attachments)) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('content')
                ->addViolation();
        }
    }
}
