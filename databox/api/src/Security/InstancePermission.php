<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Basket\Basket;
use App\Entity\Core\Workspace;
use App\Entity\Profile\Profile;
use App\Entity\SavedSearch\SavedSearch;
use App\Security\Voter\DataboxExtraPermissionInterface;

/**
 * Capabilities granted at the instance level, outside any workspace.
 *
 * Each one is a metadata of an ACE set on an object type without object id
 * (an ACE "on every object of the type"). A metadata rather than a mask bit:
 * the bits of such an ACE grant the permission on every object of the type.
 */
final class InstancePermission
{
    public const string CREATE_WORKSPACE = 'INSTANCE_CREATE_WORKSPACE';
    public const string SHARE_PROFILE = 'INSTANCE_SHARE_PROFILE';
    public const string SHARE_BASKET = 'INSTANCE_SHARE_BASKET';
    public const string PUBLISH_SAVED_SEARCH = 'INSTANCE_PUBLISH_SAVED_SEARCH';

    /**
     * @var array<string, array{class-string, int}>
     */
    private const array DEFINITIONS = [
        self::CREATE_WORKSPACE => [Workspace::class, DataboxExtraPermissionInterface::PERM_CREATE_WORKSPACE],
        self::SHARE_PROFILE => [Profile::class, DataboxExtraPermissionInterface::PERM_SHARE_PROFILE],
        self::SHARE_BASKET => [Basket::class, DataboxExtraPermissionInterface::PERM_SHARE_BASKET],
        self::PUBLISH_SAVED_SEARCH => [SavedSearch::class, DataboxExtraPermissionInterface::PERM_PUBLISH_SAVED_SEARCH],
    ];

    /**
     * Capability names as exposed by the API.
     */
    public const array API_KEYS = [
        self::CREATE_WORKSPACE => 'createWorkspace',
        self::SHARE_PROFILE => 'shareProfile',
        self::SHARE_BASKET => 'shareBasket',
        self::PUBLISH_SAVED_SEARCH => 'publishSavedSearch',
    ];

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function isInstancePermission(string $attribute): bool
    {
        return isset(self::DEFINITIONS[$attribute]);
    }

    /**
     * @return class-string
     */
    public static function getEntityClass(string $attribute): string
    {
        return self::getDefinition($attribute)[0];
    }

    public static function getMetadata(string $attribute): int
    {
        return self::getDefinition($attribute)[1];
    }

    /**
     * @return array{class-string, int}
     */
    private static function getDefinition(string $attribute): array
    {
        return self::DEFINITIONS[$attribute] ?? throw new \InvalidArgumentException(sprintf('Unknown instance permission "%s"', $attribute));
    }
}
