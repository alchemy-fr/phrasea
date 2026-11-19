<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Checks the identifiers coming from a request before they reach a `uuid` column,
 * where Postgres rejects a malformed value with an SQL error that aborts the transaction.
 */
final readonly class UuidUtil
{
    /**
     * Only the canonical form: `Uuid::isValid()` alone also accepts the `urn:uuid:` and `{…}` forms, which Postgres refuses.
     */
    public static function isValid(mixed $id): bool
    {
        return \is_string($id) && 36 === \strlen($id) && Uuid::isValid($id);
    }

    /**
     * For a query parameter or a body field: a malformed ID is a bad request (400).
     */
    public static function assertValid(mixed $id, string $name): string
    {
        if (!self::isValid($id)) {
            throw new BadRequestHttpException(sprintf('Invalid "%s"', $name));
        }

        return $id;
    }

    /**
     * For a path segment: a malformed ID designates no resource (404).
     */
    public static function assertValidOrNotFound(mixed $id, string $name): string
    {
        if (!self::isValid($id)) {
            throw new NotFoundHttpException(sprintf('Invalid "%s"', $name));
        }

        return $id;
    }
}
