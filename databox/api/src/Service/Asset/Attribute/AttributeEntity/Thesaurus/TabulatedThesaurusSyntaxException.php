<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Thesaurus;

final class TabulatedThesaurusSyntaxException extends \InvalidArgumentException
{
    /**
     * @param list<array{line: int, message: string}> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct("Syntax error:\n".implode("\n", array_map(
            fn (array $e): string => sprintf('Line %d: %s', $e['line'], $e['message']),
            $errors,
        )));
    }

    /**
     * @return list<array{line: int, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
