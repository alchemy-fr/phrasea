<?php

declare(strict_types=1);

namespace App\Databox;

final class DataboxException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isAccessDenied(): bool
    {
        return in_array($this->statusCode, [401, 403], true);
    }
}
