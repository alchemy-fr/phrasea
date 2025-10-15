<?php

namespace App\Service\Asset\RenditionBuild\Exception;

use Alchemy\Workflow\Exception\JobSkipExceptionInterface;

class RenditionBuildException extends \RuntimeException implements JobSkipExceptionInterface
{
    public function __construct(
        private readonly bool $skip,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function shouldSkipJob(): bool
    {
        return $this->skip;
    }
}
