<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ThreadMessageInput
{
    #[Assert\NotBlank]
    public ?string $content = null;

    public ?string $threadKey = null;

    public ?string $threadId = null;

    #[Assert\Callback]
    public function validateThreadKeyOrThreadId(): void
    {
        if (null === $this->threadKey && null === $this->threadId) {
            throw new \InvalidArgumentException('You must provide either a "threadKey" or a "threadId"');
        }
    }
}
