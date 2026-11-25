<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The entities are the values of the entity lists: the IDs that the "entity" attributes of the assets hold.
 */
final readonly class EntityTools
{
    private const string STATUS = 'Moderation status: 0 = approved, 1 = pending (proposed by a user, waiting for approval), 2 = rejected';

    private const array TRANSLATIONS = [
        'type' => ['object', 'null'],
        'description' => 'Translations of the value by locale, e.g. {"fr": "Tour Eiffel"}, when the list has translations. Replaces the current translations.',
        'additionalProperties' => ['type' => 'string'],
    ];

    private const array SYNONYMS = [
        'type' => ['object', 'null'],
        'description' => 'Synonyms by locale ("_" for any locale), e.g. {"_": ["Eiffel"], "fr": ["Dame de fer"]}, when the list has synonyms: a search on a synonym finds the value. Replaces the current synonyms.',
        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
    ];

    private const array DIRECTION = ['type' => 'string', 'enum' => ['asc', 'desc']];

    private const array ORDER = [
        'type' => ['object', 'null'],
        'description' => 'Sort, e.g. {"value": "asc"} or {"createdAt": "desc"}; by value when unset',
        'properties' => [
            'value' => self::DIRECTION,
            'createdAt' => self::DIRECTION,
            'updatedAt' => self::DIRECTION,
            'status' => self::DIRECTION,
            'position' => self::DIRECTION,
        ],
        'additionalProperties' => false,
    ];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_entities',
        description: 'List or search the entities (values) of entity lists, e.g. to get the IDs to give to an entity attribute (set_asset_attributes) or to compare it with in AQL. Get the list of an entity definition with get_attribute_definition (its "entityList").',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the entities of this entity list')]
        ?string $listId = null,
        #[Schema(description: 'Only the entities of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Search-as-you-type on the value, in the search index (the status and the order are then ignored)')]
        ?string $query = null,
        #[Schema(description: 'Contains match on the value')]
        ?string $value = null,
        #[Schema(description: self::STATUS, enum: [0, 1, 2])]
        ?int $status = null,
        #[Schema(description: 'Also count the attribute values referencing each entity ("usage")')]
        bool $withUsage = false,
        #[Schema(definition: self::ORDER)]
        ?array $order = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 30,
    ): array {
        $params = ApiClient::withoutNulls([
            'list' => $listId,
            'workspace' => $workspaceId,
            'query' => $query,
            'value' => $value,
            'status' => $status,
            'usage' => $withUsage ? 'true' : null,
            'page' => $page,
            'limit' => $limit,
        ]);
        foreach ($order ?? ['value' => 'asc'] as $field => $direction) {
            $params[sprintf('order[%s]', $field)] = $direction;
        }

        return $this->client->getCollection('/attribute-entities', $params);
    }

    #[McpTool(
        name: 'get_entity',
        description: 'Get an entity (a value of an entity list) with its translations, synonyms, emoji and color.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Entity ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/attribute-entities/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_entity',
        description: 'Add an entity (a value) to an entity list. When the list does not approve the new values automatically ("approveNewValues"), a value added by a user who cannot manage the list stays pending until a moderator approves it. The emoji, color, translations and synonyms are only shown when the list enables them (get_entity_list).',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Entity list ID (UUID)')]
        string $listId,
        string $value,
        #[Schema(definition: self::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(definition: self::SYNONYMS)]
        ?array $synonyms = null,
        #[Schema(description: 'A single emoji')]
        ?string $emoji = null,
        #[Schema(description: 'CSS color, e.g. "#ff8800"')]
        ?string $color = null,
        #[Schema(description: 'Free text telling apart the values with the same name (homonyms)')]
        ?string $note = null,
        #[Schema(description: 'ID of the value in an external system, unique in the list')]
        ?string $externalId = null,
        #[Schema(description: self::STATUS, enum: [0, 1, 2])]
        ?int $status = null,
    ): array {
        return $this->client->post('/attribute-entities', ApiClient::withoutNulls([
            'list' => '/entity-lists/'.$listId,
            'value' => $value,
            'translations' => $translations,
            'synonyms' => $synonyms,
            'emoji' => $emoji,
            'color' => $color,
            'note' => $note,
            'externalId' => $externalId,
            'status' => $status,
        ]));
    }

    #[McpTool(
        name: 'update_entity',
        description: 'Update an entity (a value of an entity list): the assets having it show the new value. Only the given fields are changed; "" removes the emoji, the color or the note. Only the moderators of the list change the status.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Entity ID (UUID)')]
        string $id,
        ?string $value = null,
        #[Schema(definition: self::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(definition: self::SYNONYMS)]
        ?array $synonyms = null,
        #[Schema(description: 'A single emoji, "" to remove it')]
        ?string $emoji = null,
        #[Schema(description: 'CSS color, e.g. "#ff8800", "" to remove it')]
        ?string $color = null,
        #[Schema(description: 'Free text telling apart the values with the same name, "" to remove it')]
        ?string $note = null,
        #[Schema(description: 'ID of the value in an external system, unique in the list')]
        ?string $externalId = null,
        #[Schema(description: self::STATUS, enum: [0, 1, 2])]
        ?int $status = null,
    ): array {
        $body = ApiClient::withoutNulls([
            'value' => $value,
            'translations' => $translations,
            'synonyms' => $synonyms,
            'note' => $note,
            'externalId' => $externalId,
            'status' => $status,
        ]);
        foreach (['emoji' => $emoji, 'color' => $color] as $key => $data) {
            if (null !== $data) {
                $body[$key] = '' !== $data ? $data : null;
            }
        }

        return $this->client->patch('/attribute-entities/'.rawurlencode($id), $body);
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_entity',
        description: 'Delete an entity (a value of an entity list): the assets lose this value. This cannot be undone; to replace a duplicate by another value, use merge_entities instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Entity ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/attribute-entities/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    #[McpTool(
        name: 'merge_entities',
        description: 'Merge duplicate entities of the same list into one: the assets having one of them get the kept entity instead, their values become synonyms of the kept one, and they are deleted. Done in the background for the assets.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function merge(
        #[Schema(description: 'ID of the entity to keep')]
        string $id,
        #[Schema(description: 'IDs of the entities merged into it (and deleted), of the same list', items: ['type' => 'string'], minItems: 1, maxItems: 100)]
        array $mergedIds,
    ): array {
        return $this->client->put('/attribute-entities/'.rawurlencode($id).'/merge', [
            'ids' => array_values(array_diff($mergedIds, [$id])),
        ]);
    }
}
