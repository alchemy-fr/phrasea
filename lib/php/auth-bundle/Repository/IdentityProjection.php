<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

/**
 * Users and groups read through the service account come with the full IdP
 * records (e-mail, attributes…): only these fields are given back to clients.
 */
final class IdentityProjection
{
    public const array USER_FIELDS = ['id', 'username', 'firstName', 'lastName'];
    public const array GROUP_FIELDS = ['id', 'name', 'path'];

    /**
     * @param array<string, mixed>|null $user
     *
     * @return array<string, mixed>|null
     */
    public static function user(?array $user): ?array
    {
        return self::project($user, self::USER_FIELDS);
    }

    /**
     * @param array<string, mixed>|null $group
     *
     * @return array<string, mixed>|null
     */
    public static function group(?array $group): ?array
    {
        return self::project($group, self::GROUP_FIELDS);
    }

    /**
     * Keeps the keys of $users (getUsersByIds() returns them indexed by ID).
     *
     * @template K of array-key
     *
     * @param array<K, array<string, mixed>|null> $users
     *
     * @return array<K, array<string, mixed>|null>
     */
    public static function users(array $users): array
    {
        return array_map(self::user(...), $users);
    }

    /**
     * @param array<string, mixed>|null $record
     * @param list<string>              $fields
     *
     * @return array<string, mixed>|null
     */
    private static function project(?array $record, array $fields): ?array
    {
        if (null === $record) {
            return null;
        }

        return array_intersect_key($record, array_flip($fields));
    }
}
