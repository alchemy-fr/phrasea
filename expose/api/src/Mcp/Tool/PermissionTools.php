<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

/**
 * Access control entries (ACE) granting permissions on publications and profiles to users and groups.
 */
final readonly class PermissionTools
{
    /**
     * The objects of config/packages/alchemy_acl.yaml.
     */
    final public const array OBJECT_TYPES = ['publication', 'profile'];

    /**
     * The enabled_permissions of config/packages/alchemy_acl.yaml.
     */
    final public const array PERMISSIONS = ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'OPERATOR', 'OWNER'];

    private const string OBJECT_TYPE = 'Kind of object: "publication" or "profile" (publication profile)';
    private const string OBJECT_ID = 'ID (UUID) of the publication or profile; unset for a permission on every object of the type (e.g. CREATE, to create publications or profiles)';
    private const string USER_TYPE = '"user" or "group"';
    private const string USER_ID = 'ID of the user or group (see search_users / search_groups); "__ALL_USERS__" with userType "user" for every signed-in user';
    private const int USERS_PAGE_SIZE = 30;

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>}
     */
    #[McpTool(
        name: 'list_publication_permissions',
        description: 'List the permissions (access control entries) granted to users and groups on a publication or a profile, or on every object of the type. Each entry gives the user or group, the bit mask and the permission names. Only the owner of the object or an admin may read them.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: self::OBJECT_TYPE, enum: self::OBJECT_TYPES)]
        string $objectType,
        #[Schema(description: self::OBJECT_ID)]
        ?string $objectId = null,
        #[Schema(description: 'With an objectId: also list the permissions granted on every object of the type, which apply to this one too')]
        bool $includeTypeWide = false,
    ): array {
        $aces = $this->client->get('/permissions/aces', ApiClient::withoutNulls([
            'objectType' => $objectType,
            // An empty objectId selects the type-wide entries only.
            'objectId' => $objectId ?? '',
            'objectIdWildcard' => null !== $objectId && $includeTypeWide ? '1' : null,
        ]));

        return ['items' => array_map(self::withPermissionNames(...), is_array($aces) ? array_values($aces) : [])];
    }

    #[McpTool(
        name: 'set_publication_permission',
        description: <<<'TXT'
            Grant permissions to a user or a group on a publication or a profile, replacing the ones it already had there. Only the owner of the object or an admin may do it.
            On a publication: VIEW = read it when its security method is "authentication"; EDIT = read and edit it and its assets; DELETE = delete it; OPERATOR = manage its share links and change its profile. On a profile: VIEW = read and use it; EDIT = edit it; DELETE = delete it. CREATE, on the whole type (no objectId), allows creating publications or profiles.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function set(
        #[Schema(description: self::OBJECT_TYPE, enum: self::OBJECT_TYPES)]
        string $objectType,
        #[Schema(description: self::USER_TYPE, enum: ['user', 'group'])]
        string $userType,
        #[Schema(description: self::USER_ID)]
        string $userId,
        #[Schema(description: 'Permissions granted', items: ['type' => 'string', 'enum' => self::PERMISSIONS], minItems: 1, uniqueItems: true)]
        array $permissions,
        #[Schema(description: self::OBJECT_ID)]
        ?string $objectId = null,
    ): array {
        return self::withPermissionNames($this->client->put('/permissions/ace', ApiClient::withoutNulls([
            'objectType' => $objectType,
            'objectId' => $objectId,
            'userType' => $userType,
            'userId' => $userId,
            'mask' => self::toMask($permissions),
        ])));
    }

    /**
     * @return array{removed: array<string, string>}
     */
    #[McpTool(
        name: 'remove_publication_permission',
        description: 'Remove every permission granted to a user or a group on a publication or a profile (or on every object of the type). Only the owner of the object or an admin may do it.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function remove(
        #[Schema(description: self::OBJECT_TYPE, enum: self::OBJECT_TYPES)]
        string $objectType,
        #[Schema(description: self::USER_TYPE, enum: ['user', 'group'])]
        string $userType,
        #[Schema(description: self::USER_ID)]
        string $userId,
        #[Schema(description: self::OBJECT_ID)]
        ?string $objectId = null,
    ): array {
        $key = ApiClient::withoutNulls([
            'objectType' => $objectType,
            'objectId' => $objectId,
            'userType' => $userType,
            'userId' => $userId,
        ]);
        $this->client->delete('/permissions/ace', $key);

        return ['removed' => $key];
    }

    /**
     * @return array{items: list<array>, offset: int, hasMore: bool}
     */
    #[McpTool(
        name: 'search_users',
        description: 'Search the users (30 per page, use "offset" for the next ones), to get the ID to grant a permission to.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function searchUsers(
        #[Schema(description: 'Search on the username, email, first and last name')]
        ?string $query = null,
        #[Schema(minimum: 0)]
        int $offset = 0,
    ): array {
        return $this->searchIdentities('/permissions/users', $query, $offset);
    }

    /**
     * @return array{items: list<array>, offset: int, hasMore: bool}
     */
    #[McpTool(
        name: 'search_groups',
        description: 'Search the groups of users (30 per page, use "offset" for the next ones), to get the ID to grant a permission to.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function searchGroups(
        #[Schema(description: 'Search on the group name')]
        ?string $query = null,
        #[Schema(minimum: 0)]
        int $offset = 0,
    ): array {
        return $this->searchIdentities('/permissions/groups', $query, $offset);
    }

    /**
     * @return array{items: list<array>, offset: int, hasMore: bool}
     */
    private function searchIdentities(string $path, ?string $query, int $offset): array
    {
        $items = $this->client->get($path, ApiClient::withoutNulls([
            'query' => $query,
            'limit' => self::USERS_PAGE_SIZE,
            'offset' => $offset,
        ]));
        $items = is_array($items) ? array_values($items) : [];

        return [
            'items' => $items,
            'offset' => $offset,
            // The identity provider does not count the matches: a full page may have a next one.
            'hasMore' => self::USERS_PAGE_SIZE === count($items),
        ];
    }

    /**
     * @param array<mixed> $permissions
     */
    private static function toMask(array $permissions): int
    {
        $mask = 0;
        foreach ($permissions as $permission) {
            if (!in_array($permission, self::PERMISSIONS, true)) {
                throw new ToolCallException(sprintf('Unknown permission "%s", expected one of: %s', is_scalar($permission) ? $permission : get_debug_type($permission), implode(', ', self::PERMISSIONS)));
            }
            $mask |= PermissionInterface::PERMISSIONS[$permission];
        }

        return $mask;
    }

    private static function withPermissionNames(array $ace): array
    {
        $mask = (int) ($ace['mask'] ?? 0);
        $ace['permissions'] = array_keys(array_filter(
            PermissionInterface::PERMISSIONS,
            static fn (int $bit): bool => ($mask & $bit) === $bit,
        ));

        return $ace;
    }
}
