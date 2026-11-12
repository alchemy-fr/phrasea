<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Exception;

/**
 * The identity provider refused to create an OAuth client: its client ID is already taken.
 */
final class ClientAlreadyExistsException extends \RuntimeException
{
    public function __construct(
        private readonly string $clientId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('A client with ID "%s" already exists', $clientId), 409, $previous);
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }
}
