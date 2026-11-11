<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class CollectionTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_collections',
        description: 'List the collections visible to the current user. Without "parentId", the root collections of the workspaces; with it, its children. "query" searches the names in the whole sub-tree ("in:trash" lists the deleted ones).',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the collections of these workspaces', items: ['type' => 'string'])]
        ?array $workspaceIds = null,
        #[Schema(description: 'Only the children of this collection')]
        ?string $parentId = null,
        #[Schema(description: 'Search on the name')]
        ?string $query = null,
        #[Schema(description: 'Also list the descendants, not only the direct children')]
        bool $deep = false,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/collections', ApiClient::withoutNulls([
            'workspaces' => null !== $workspaceIds ? array_values($workspaceIds) : null,
            'parent' => $parentId,
            'query' => $query,
            'deep' => $deep ? 'true' : null,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_collection',
        description: 'Get a collection.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Collection ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/collections/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_collection',
        description: 'Create a collection at the root of a workspace or under a parent collection. With a "key" already used in the workspace, the existing collection is updated instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $name,
        #[Schema(description: 'Workspace ID (UUID), for a root collection')]
        ?string $workspaceId = null,
        #[Schema(description: 'ID of the parent collection')]
        ?string $parentId = null,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(description: 'Technical key, unique in the workspace')]
        ?string $key = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->post('/collections', ApiClient::withoutNulls([
            'name' => $name,
            'workspace' => null !== $workspaceId ? '/workspaces/'.$workspaceId : null,
            'parent' => null !== $parentId ? '/collections/'.$parentId : null,
            'privacyLabel' => $privacy,
            'key' => $key,
            'translations' => $translations,
        ]));
    }

    #[McpTool(
        name: 'update_collection',
        description: 'Update a collection. Only the given fields are changed; use move_collection to change its parent.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Collection ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->patch('/collections/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'privacyLabel' => $privacy,
            'translations' => $translations,
        ]));
    }

    /**
     * @return array{moved: string, parentId: string|null}
     */
    #[McpTool(
        name: 'move_collection',
        description: 'Move a collection, with its sub-tree, under another collection of the workspace or to its root.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function move(
        #[Schema(description: 'Collection ID (UUID)')]
        string $id,
        #[Schema(description: 'ID of the new parent collection; unset to move it to the root of the workspace')]
        ?string $parentId = null,
    ): array {
        $this->client->put(sprintf('/collections/%s/move/%s', rawurlencode($id), null !== $parentId ? rawurlencode($parentId) : 'root'));

        return ['moved' => $id, 'parentId' => $parentId];
    }

    /**
     * @return array{trashed: list<string>}
     */
    #[McpTool(
        name: 'delete_collections',
        description: 'Move collections, with their sub-collections, to the trash of their workspace. They can be restored with restore_collections until the trash retention delay of the workspace expires.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Collection IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/collections/delete-multiple', ['ids' => $ids]);

        return ['trashed' => $ids];
    }

    /**
     * @return array{restored: list<string>}
     */
    #[McpTool(
        name: 'restore_collections',
        description: 'Restore collections from the trash.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function restore(
        #[Schema(description: 'Collection IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/collections/restore-multiple', ['ids' => $ids]);

        return ['restored' => $ids];
    }
}
