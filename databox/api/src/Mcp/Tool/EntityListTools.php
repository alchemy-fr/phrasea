<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class EntityListTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_entity_lists',
        description: 'List the entity lists of the workspaces visible to the current user, by name. An entity list holds the values (people, places, keywords…) proposed by the attribute definitions of type "entity".',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the entity lists of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Contains match on the name')]
        ?string $name = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/entity-lists', ApiClient::withoutNulls([
            'workspace' => $workspaceId,
            'name' => $name,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'get_entity_list',
        description: 'Get an entity list with its options.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/entity-lists/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_entity_list',
        description: 'Create an entity list in a workspace.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'Whether users can add values while editing an attribute')]
        ?bool $allowNewValues = null,
        #[Schema(description: 'Whether the values added by users must be approved')]
        ?bool $approveNewValues = null,
        #[Schema(description: 'Whether the values have an emoji')]
        ?bool $withEmojis = null,
        #[Schema(description: 'Whether the values have a color')]
        ?bool $withColors = null,
        #[Schema(description: 'Whether the values have an image')]
        ?bool $withImages = null,
        #[Schema(description: 'Whether the values are translated')]
        ?bool $withTranslations = null,
        #[Schema(description: 'Whether the values have synonyms')]
        ?bool $withSynonyms = null,
    ): array {
        return $this->client->post('/entity-lists', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'allowNewValues' => $allowNewValues,
            'approveNewValues' => $approveNewValues,
            'withEmojis' => $withEmojis,
            'withColors' => $withColors,
            'withImages' => $withImages,
            'withTranslations' => $withTranslations,
            'withSynonyms' => $withSynonyms,
        ]));
    }

    #[McpTool(
        name: 'update_entity_list',
        description: 'Update an entity list. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
        ?string $name = null,
        ?bool $allowNewValues = null,
        ?bool $approveNewValues = null,
        ?bool $withEmojis = null,
        ?bool $withColors = null,
        ?bool $withImages = null,
        ?bool $withTranslations = null,
        ?bool $withSynonyms = null,
    ): array {
        return $this->client->patch('/entity-lists/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'allowNewValues' => $allowNewValues,
            'approveNewValues' => $approveNewValues,
            'withEmojis' => $withEmojis,
            'withColors' => $withColors,
            'withImages' => $withImages,
            'withTranslations' => $withTranslations,
            'withSynonyms' => $withSynonyms,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_entity_list',
        description: 'Delete an entity list with its values. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/entity-lists/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
