<?php

declare(strict_types=1);

namespace App\Service\Admin;

final class InvalidClientThemeException extends \InvalidArgumentException
{
    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('Invalid client theme: '.implode('; ', array_map(
            fn (array $v): string => sprintf('%s: %s', $v['propertyPath'], $v['message']),
            $violations
        )));
    }

    /**
     * @return list<array{propertyPath: string, message: string}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
