<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Retry;

final readonly class RetryReport
{
    public function __construct(
        public int $retried = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public ?string $firstError = null,
        public ?string $stoppedBy = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->retried
            && 0 === $this->skipped
            && 0 === $this->failed
            && null === $this->stoppedBy;
    }
}
