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
        #[Schema(description: 'Whether the values added by users are approved automatically; otherwise those added by users who cannot manage the list wait for an approval')]
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

    #[McpTool(
        name: 'import_entity_list_values',
        description: 'Add values to an entity list in bulk. "raw": one value per line, the values already in the list are skipped. "csv": a header line then one entity per line; the columns are "value" (required), "id", "external_id", "emoji", "color", "status" (0 = approved, 1 = pending, 2 = rejected) and "translation_<locale>" (e.g. "translation_fr"). A line updates the entity of its "id", else the one of its "external_id", else the one with the same value, or adds it; any invalid line rejects the whole import. Read the current values with export_entity_list_values.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function import(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
        #[Schema(description: 'Format of "data"', enum: ['raw', 'csv'])]
        string $format,
        #[Schema(description: 'The values, in the given format')]
        string $data,
    ): array {
        return $this->client->post('/entity-lists/'.rawurlencode($id).'/import', [
            'format' => $format,
            'data' => $data,
        ]);
    }

    /**
     * @return array{format: string, content: string}
     */
    #[McpTool(
        name: 'export_entity_list_values',
        description: 'Export every value of an entity list, with its ID, emoji, color, status (0 = approved, 1 = pending, 2 = rejected), external ID and translations. The CSV export is in the format of import_entity_list_values.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function export(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
        #[Schema(enum: ['csv', 'json'])]
        string $format = 'csv',
        #[Schema(description: 'Export the values in this locale (their translation) instead of every translation')]
        ?string $locale = null,
    ): array {
        $file = $this->client->postForFile('/entity-lists/'.rawurlencode($id).'/export', ApiClient::withoutNulls([
            'format' => $format,
            'locale' => $locale,
        ]));

        return ['format' => $format, 'content' => $file['content']];
    }

    /**
     * @return array{cleared: string}
     */
    #[McpTool(
        name: 'clear_entity_list',
        description: 'Delete every value of an entity list, which stays empty: the assets lose these values. Done in the background. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function clear(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $id,
    ): array {
        $this->client->post('/entity-lists/'.rawurlencode($id).'/clear');

        return ['cleared' => $id];
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
