<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Exception;

/**
 * The identity provider refused to create or rename a group: a sibling group has the same name.
 */
final class GroupAlreadyExistsException extends \RuntimeException
{
    public function __construct(
        private readonly string $name,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('A group named "%s" already exists', $name), 409, $previous);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
