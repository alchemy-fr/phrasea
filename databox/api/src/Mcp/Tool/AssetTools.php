<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

final readonly class AssetTools
{
    private const string MACHINE_ORIGIN = 'machine';

    private const string AQL = <<<'TXT'
        AQL conditions, combined with AND. A condition is `field OPERATOR [value]`; a field is an attribute slug or a built-in field (@tag, @collection, @workspace, @createdAt, @editedAt, @filename, @type, @owner…).
        Operators (uppercase): IS, IS NOT, <, <=, >, >=, BETWEEN a AND b, IS ANY OF ("a", "b"), IS NONE OF (…), HAS ALL OF (…), IS EMPTY, IS NOT EMPTY, CONTAINS, STARTS WITH, ENDS WITH (case-insensitive, add CASE SENSITIVE after the value otherwise), MATCHES (full-text, every word required), WITHIN CIRCLE (lat, lng, "1500m"), WITHIN RECTANGLE (tlLat, tlLng, brLat, brLng).
        Values are quoted strings, numbers, true, false, null or functions (NOW(), DATE_SUB(NOW(), "P7D")…); combine with AND, OR, NOT and parentheses. On dates, IS matches a prefix: @createdAt IS "2024-03" is the whole month.
        Example: title STARTS WITH "Report" AND (@tag HAS ALL OF ("a", "b") OR description IS EMPTY)
        TXT;

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'search_assets',
        description: 'Search the assets the current user can see (Elasticsearch), with a full-text query and/or AQL conditions, by relevance or by the given order.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function search(
        #[Schema(description: 'Full-text query')]
        ?string $query = null,
        #[Schema(description: self::AQL, items: ['type' => 'string'])]
        ?array $conditions = null,
        #[Schema(description: 'Only the assets of these workspaces', items: ['type' => 'string'])]
        ?array $workspaceIds = null,
        #[Schema(description: 'Only the assets of these collections or of their sub-collections', items: ['type' => 'string'])]
        ?array $parentIds = null,
        #[Schema(description: 'Only the assets directly in this collection')]
        ?string $collectionId = null,
        #[Schema(description: 'Only the assets of this story')]
        ?string $storyId = null,
        #[Schema(description: 'Tag IDs the assets must all have', items: ['type' => 'string'])]
        ?array $tagsMust = null,
        #[Schema(description: 'Tag IDs the assets must not have', items: ['type' => 'string'])]
        ?array $tagsMustNot = null,
        #[Schema(
            type: 'object',
            description: 'Sort, by attribute slug or built-in field, e.g. {"@createdAt": "desc"}; by relevance when unset',
            additionalProperties: ['type' => 'string', 'enum' => ['asc', 'desc']],
        )]
        ?array $order = null,
        #[Schema(description: 'Also return the facets: the value counts of the facet-enabled attributes')]
        bool $withFacets = false,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 20,
    ): array {
        return $this->client->getCollection('/assets', ApiClient::withoutNulls([
            'query' => $query,
            'conditions' => null !== $conditions ? array_values($conditions) : null,
            'workspaces' => null !== $workspaceIds ? array_values($workspaceIds) : null,
            'parents' => null !== $parentIds ? array_values($parentIds) : null,
            'collection' => $collectionId,
            'story' => $storyId,
            'tags_must' => null !== $tagsMust ? array_values($tagsMust) : null,
            'tags_must_not' => null !== $tagsMustNot ? array_values($tagsMustNot) : null,
            'order' => $order,
            'page' => $page,
            'limit' => $limit,
        ]), $withFacets ? ['facets'] : []);
    }

    #[McpTool(
        name: 'get_asset',
        description: 'Get an asset with its attributes, tags, collections and main renditions.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_asset_from_url',
        description: 'Create an asset in a workspace or a collection from the URL of its file, or an empty story. The renditions are built in the background. With a "key" already used in the workspace, the existing asset is updated instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createFromUrl(
        #[Schema(description: 'Workspace ID (UUID); not needed with a collection')]
        ?string $workspaceId = null,
        #[Schema(description: 'ID of the collection to create the asset in')]
        ?string $collectionId = null,
        #[Schema(description: 'Public URL of the file; required unless creating a story')]
        ?string $url = null,
        #[Schema(description: 'File name, e.g. "sunset.jpg" (taken from the URL otherwise)')]
        ?string $originalName = null,
        #[Schema(description: 'Copy the file into the Phrasea storage; otherwise the asset keeps pointing to the URL, which must stay reachable')]
        bool $importFile = false,
        #[Schema(description: 'Name of the asset, filling the attributes defined to be filled from it (usually the title)')]
        ?string $name = null,
        #[Schema(description: 'Tag IDs', items: ['type' => 'string'])]
        ?array $tagIds = null,
        #[Schema(definition: ToolSchemas::ATTRIBUTES)]
        ?array $attributes = null,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(description: 'Technical key, unique in the workspace')]
        ?string $key = null,
        #[Schema(description: 'Create a story (an asset grouping other assets) instead of a file asset')]
        bool $isStory = false,
        #[Schema(description: 'Story only: IDs of existing assets added to the story, in this order', items: ['type' => 'string'])]
        ?array $storyAssetIds = null,
        #[Schema(description: 'Name of the vendor or model writing the attribute values, e.g. "claude"')]
        ?string $originVendor = null,
    ): array {
        if (null === $workspaceId && null === $collectionId) {
            throw new ToolCallException('Give a workspaceId or a collectionId.');
        }

        return $this->client->post('/assets', ApiClient::withoutNulls([
            'workspace' => null !== $workspaceId ? '/workspaces/'.$workspaceId : null,
            'collection' => null !== $collectionId ? '/collections/'.$collectionId : null,
            'sourceFile' => null !== $url ? ApiClient::withoutNulls([
                'url' => $url,
                'originalName' => $originalName,
                'importFile' => $importFile,
            ]) : null,
            'name' => $name,
            'tags' => null !== $tagIds ? self::iris('/tags/', $tagIds) : null,
            'attributes' => null !== $attributes ? array_map(
                static fn (array $attribute): array => self::machineValue([
                    'definitionId' => $attribute['definitionId'] ?? null,
                    'value' => $attribute['value'] ?? null,
                    'locale' => $attribute['locale'] ?? null,
                    'position' => $attribute['position'] ?? null,
                ], $originVendor),
                array_values($attributes),
            ) : null,
            'privacyLabel' => $privacy,
            'key' => $key,
            'isStory' => $isStory ?: null,
            'storyAssets' => null !== $storyAssetIds ? array_values($storyAssetIds) : null,
        ]));
    }

    #[McpTool(
        name: 'update_asset',
        description: 'Update the privacy or the tags of an asset. Only the given fields are changed. To change its title or any other metadata, use set_asset_attributes.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(description: 'Tag IDs, replacing the current tags ([] removes them all)', items: ['type' => 'string'])]
        ?array $tagIds = null,
    ): array {
        return $this->client->patch('/assets/'.rawurlencode($id), ApiClient::withoutNulls([
            'privacyLabel' => $privacy,
            'tags' => null !== $tagIds ? self::iris('/tags/', $tagIds) : null,
        ]));
    }

    #[McpTool(
        name: 'set_asset_attributes',
        description: 'Change the attribute values (metadata) of an asset. The values are recorded as written by a machine. Get the definition IDs and types with list_attribute_definitions; the asset is returned with its new values.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function setAttributes(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
        #[Schema(definition: ToolSchemas::ATTRIBUTE_ACTIONS)]
        array $actions,
        #[Schema(description: 'Name of the vendor or model writing the values, e.g. "claude"')]
        ?string $originVendor = null,
    ): array {
        return $this->client->post('/assets/'.rawurlencode($id).'/attributes', [
            'actions' => array_map(static fn (array $action): array => self::machineValue([
                'action' => $action['action'] ?? 'set',
                'definitionId' => $action['definitionId'] ?? null,
                'value' => $action['value'] ?? null,
                'locale' => $action['locale'] ?? null,
                'id' => $action['id'] ?? null,
                'confidence' => $action['confidence'] ?? null,
            ], $originVendor), array_values($actions)),
        ]);
    }

    /**
     * @return array{moved: list<string>}
     */
    #[McpTool(
        name: 'move_assets',
        description: 'Move assets into a collection, or to the root of their workspace, leaving their other collections. Assets cannot change workspace. Done in the background.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function move(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
        #[Schema(description: 'ID of the destination collection')]
        ?string $collectionId = null,
        #[Schema(description: 'ID of the workspace, to move the assets to its root')]
        ?string $workspaceId = null,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/assets/move', [
            'ids' => $ids,
            'destination' => self::destination($collectionId, $workspaceId),
        ]);

        return ['moved' => $ids];
    }

    /**
     * @return array{copied: list<string>}
     */
    #[McpTool(
        name: 'copy_assets',
        description: 'Copy assets into a collection or the root of a workspace, possibly another workspace. Done in the background.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function copy(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
        #[Schema(description: 'ID of the destination collection')]
        ?string $collectionId = null,
        #[Schema(description: 'ID of the destination workspace, to copy the assets to its root')]
        ?string $workspaceId = null,
        #[Schema(description: 'Share the file of the original asset instead of copying it')]
        bool $byReference = false,
        #[Schema(description: 'Copy the attribute values')]
        bool $withAttributes = false,
        #[Schema(description: 'Copy the tags')]
        bool $withTags = false,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/assets/copy', [
            'ids' => $ids,
            'destination' => self::destination($collectionId, $workspaceId),
            'byReference' => $byReference,
            'withAttributes' => $withAttributes,
            'withTags' => $withTags,
        ]);

        return ['copied' => $ids];
    }

    /**
     * @return array{added: list<string>}
     */
    #[McpTool(
        name: 'add_assets_to_collection',
        description: 'Add assets to a collection, or to a story, keeping their other collections.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function addToCollection(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
        #[Schema(description: 'ID of the collection')]
        ?string $collectionId = null,
        #[Schema(description: 'ID of the story asset, instead of a collection')]
        ?string $storyId = null,
    ): array {
        if ((null === $collectionId) === (null === $storyId)) {
            throw new ToolCallException('Give either a collectionId or a storyId.');
        }

        $ids = array_values($ids);
        $this->client->post('/assets/add-to-collection', [
            'ids' => $ids,
            'destination' => null !== $collectionId ? '/collections/'.$collectionId : '/assets/'.$storyId,
        ]);

        return ['added' => $ids];
    }

    /**
     * @return array{removed: string, collectionId: string}
     */
    #[McpTool(
        name: 'remove_asset_from_collection',
        description: 'Remove an asset from a collection. The asset itself is kept.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function removeFromCollection(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
        #[Schema(description: 'Collection ID (UUID)')]
        string $collectionId,
    ): array {
        $this->client->delete(sprintf('/assets/%s/collections/%s', rawurlencode($id), rawurlencode($collectionId)));

        return ['removed' => $id, 'collectionId' => $collectionId];
    }

    /**
     * @return array{trashed: list<string>}
     */
    #[McpTool(
        name: 'delete_assets',
        description: 'Move assets to the trash of their workspace. They can be restored with restore_assets until the trash retention delay of the workspace expires.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/assets/delete-multiple', ['ids' => $ids]);

        return ['trashed' => $ids];
    }

    /**
     * @return array{restored: list<string>}
     */
    #[McpTool(
        name: 'restore_assets',
        description: 'Restore assets from the trash.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function restore(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/assets/restore-multiple', ['ids' => $ids]);

        return ['restored' => $ids];
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'find_similar_assets',
        description: 'Find the assets visually similar to an asset (when the similarity search is enabled), most similar first.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function findSimilar(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 10,
    ): array {
        return $this->client->getCollection('/assets/'.rawurlencode($id).'/similar', ['limit' => $limit], ['scores']);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function iris(string $prefix, array $ids): array
    {
        return array_map(static fn (string $id): string => $prefix.$id, array_values($ids));
    }

    private static function destination(?string $collectionId, ?string $workspaceId): string
    {
        if ((null === $collectionId) === (null === $workspaceId)) {
            throw new ToolCallException('Give either a collectionId or a workspaceId.');
        }

        return null !== $collectionId ? '/collections/'.$collectionId : '/workspaces/'.$workspaceId;
    }

    /**
     * Values written by the model are flagged as such, unlike those of the users.
     */
    private static function machineValue(array $value, ?string $originVendor): array
    {
        return ApiClient::withoutNulls($value + [
            'origin' => self::MACHINE_ORIGIN,
            'originVendor' => $originVendor,
        ]);
    }
}
