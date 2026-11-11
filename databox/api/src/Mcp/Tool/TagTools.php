<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class TagTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_tags',
        description: 'List the tags of the workspaces visible to the current user. Tags are labels set on assets, filtered in the search with "@tag" or tags_must.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the tags of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Search-as-you-type on the name or any translation of it')]
        ?string $query = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/tags', ApiClient::withoutNulls([
            'workspace' => $workspaceId,
            'query' => $query,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_tag',
        description: 'Get a tag.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Tag ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/tags/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_tag',
        description: 'Create a tag in a workspace. When the workspace already has a tag of this name, that tag is updated and returned instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'Color, e.g. "#ff0000"')]
        ?string $color = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->post('/tags', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'color' => $color,
            'translations' => $translations,
        ]));
    }

    #[McpTool(
        name: 'update_tag',
        description: 'Update a tag. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Tag ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: 'Color, e.g. "#ff0000"')]
        ?string $color = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->patch('/tags/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'color' => $color,
            'translations' => $translations,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_tag',
        description: 'Delete a tag: it is removed from every asset. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Tag ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/tags/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
