<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * A data template holds attribute values (and a privacy) that the users apply to the assets they upload into a
 * workspace or a collection, instead of typing them again.
 */
final readonly class AssetDataTemplateTools
{
    private const string INCLUDE_CHILDREN = 'With a collection: also offer the template in its sub-collections. Set to false when not given';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_data_templates',
        description: 'List the data templates offered in a workspace, or in a collection (its own, those of its ancestors including their sub-collections, and those of the workspace). Give a workspace or a collection.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Workspace ID (UUID)')]
        ?string $workspaceId = null,
        #[Schema(description: 'Collection ID (UUID)')]
        ?string $collectionId = null,
        #[Schema(description: 'Search on the name')]
        ?string $query = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/asset-data-templates', ApiClient::withoutNulls([
            'workspace' => $workspaceId,
            'collection' => $collectionId,
            'query' => $query,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_asset_data_template',
        description: 'Get a data template with its attribute values.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Data template ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/asset-data-templates/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_asset_data_template',
        description: 'Create a data template in a workspace, offered at upload in the whole workspace or in a collection only.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(definition: ToolSchemas::ATTRIBUTES)]
        ?array $attributes = null,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(description: 'Offer the template in this collection only, instead of the whole workspace')]
        ?string $collectionId = null,
        #[Schema(description: self::INCLUDE_CHILDREN)]
        bool $includeCollectionChildren = false,
        #[Schema(description: 'Offer the template to every user of the workspace, not only to its owner')]
        ?bool $public = null,
    ): array {
        return $this->client->post('/asset-data-templates', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'attributes' => self::attributes($attributes),
            'privacy' => self::privacy($privacy),
            'collection' => null !== $collectionId ? '/collections/'.$collectionId : null,
            'includeCollectionChildren' => $includeCollectionChildren,
            'public' => $public,
        ]));
    }

    #[McpTool(
        name: 'update_asset_data_template',
        description: 'Update a data template. Only the given fields are changed, except includeCollectionChildren (false when not given); the given attributes replace all those of the template.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Data template ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(definition: ToolSchemas::ATTRIBUTES)]
        ?array $attributes = null,
        #[Schema(definition: ToolSchemas::PRIVACY)]
        ?string $privacy = null,
        #[Schema(description: 'Offer the template in this collection only')]
        ?string $collectionId = null,
        #[Schema(description: self::INCLUDE_CHILDREN)]
        bool $includeCollectionChildren = false,
        #[Schema(description: 'Offer the template to every user of the workspace, not only to its owner')]
        ?bool $public = null,
    ): array {
        return $this->client->patch('/asset-data-templates/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'attributes' => self::attributes($attributes),
            'privacy' => self::privacy($privacy),
            'collection' => null !== $collectionId ? '/collections/'.$collectionId : null,
            'includeCollectionChildren' => $includeCollectionChildren,
            'public' => $public,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_asset_data_template',
        description: 'Delete a data template. The assets it was applied to keep their values. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Data template ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/asset-data-templates/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    private static function attributes(?array $attributes): ?array
    {
        return null !== $attributes ? array_map(static fn (array $attribute): array => ApiClient::withoutNulls([
            'definitionId' => $attribute['definitionId'] ?? null,
            'value' => $attribute['value'] ?? null,
            'locale' => $attribute['locale'] ?? null,
            'position' => $attribute['position'] ?? null,
        ]), array_values($attributes)) : null;
    }

    /**
     * The template stores the privacy as its integer value.
     */
    private static function privacy(?string $privacy): ?int
    {
        if (null === $privacy) {
            return null;
        }

        $value = array_search($privacy, WorkspaceItemPrivacyInterface::KEYS, true);

        return false !== $value ? $value : null;
    }
}
