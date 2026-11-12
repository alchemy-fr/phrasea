<?php

declare(strict_types=1);

namespace App\Form\Builder;

final class InvalidFormDefinitionException extends \InvalidArgumentException
{
    public function __construct(string $message, public readonly ?string $path = null)
    {
        parent::__construct($message);
    }
}
