<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class AttributeDefinitionTools
{
    private const array TYPES = [
        'text', 'textarea', 'html', 'code', 'json', 'keyword', 'number', 'boolean', 'date', 'date_time', 'duration',
        'color', 'geo_point', 'ip', 'filesize', 'entity', 'web_vtt', 'id', 'collection_path', 'asset_status', 'privacy',
    ];

    private const array INITIAL_VALUES = [
        'type' => ['object', 'null'],
        'description' => 'Twig templates computing the value when the asset is created, by locale ("_" for a non-translatable definition), e.g. {"_": "{{ file.filename }}"}',
        'additionalProperties' => ['type' => 'string'],
    ];

    private const array FALLBACK = [
        'type' => ['object', 'null'],
        'description' => 'Twig templates of the value shown when the asset has none (not stored), by locale ("_" for a non-translatable definition)',
        'additionalProperties' => ['type' => 'string'],
    ];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_attribute_definitions',
        description: 'List the attribute definitions (metadata fields) of the workspaces visible to the current user, in their display order. Use their IDs to set asset attributes and their slugs in AQL conditions.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listDefinitions(
        #[Schema(description: 'Only the definitions of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Contains match on the name or any translation of it')]
        ?string $query = null,
        #[Schema(description: 'Only the definitions of this type', enum: self::TYPES)]
        ?string $type = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(description: 'Only the enabled (true) or disabled (false) definitions')]
        ?bool $enabled = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 100,
    ): array {
        return $this->client->getCollection('/attribute-definitions', ApiClient::withoutNulls([
            'workspaceId' => $workspaceId,
            'query' => $query,
            'type' => $type,
            'target' => $target,
            'enabled' => null !== $enabled ? ($enabled ? 'true' : 'false') : null,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_attribute_definition',
        description: 'Get an attribute definition.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getDefinition(
        #[Schema(description: 'Attribute definition ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/attribute-definitions/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_attribute_definition',
        description: 'Create an attribute definition (a metadata field) in a workspace. With a "key" already used in the workspace, the existing definition is updated instead. An "entity" definition needs an entity list.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createDefinition(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'ID of the attribute policy controlling who can read and edit it')]
        string $policyId,
        #[Schema(description: 'Type of the values', enum: self::TYPES)]
        string $type = 'text',
        #[Schema(description: 'ID of the entity list providing the values, for the "entity" type')]
        ?string $entityListId = null,
        #[Schema(description: 'Whether an asset can have several values')]
        ?bool $multiple = null,
        #[Schema(description: 'Whether the value is given per locale')]
        ?bool $translatable = null,
        ?bool $searchable = null,
        #[Schema(description: 'Whether the search shows a facet of its values')]
        ?bool $facetEnabled = null,
        #[Schema(description: 'Whether the search results can be sorted by it')]
        ?bool $sortable = null,
        #[Schema(description: 'Whether the search suggests its values')]
        ?bool $suggest = null,
        ?bool $enabled = null,
        #[Schema(description: 'Whether its values can be changed')]
        ?bool $editable = null,
        #[Schema(description: 'Whether its values can be changed in the user interface')]
        ?bool $editableInGui = null,
        #[Schema(description: 'Whether values not matching the type are kept (flagged as invalid)')]
        ?bool $allowInvalid = null,
        #[Schema(description: 'Whether the users can draw annotations on the asset for a value')]
        ?bool $annotationsEnabled = null,
        #[Schema(description: 'Whether it is filled with the asset name (e.g. the file name) when the asset is created')]
        ?bool $fillFromName = null,
        #[Schema(definition: self::INITIAL_VALUES)]
        ?array $initialValues = null,
        #[Schema(definition: self::FALLBACK)]
        ?array $fallback = null,
        #[Schema(description: 'Metadata tags read, in this order, to initialize it (first found wins), e.g. ["XMP-dc:Title", "IPTC:ObjectName"]', items: ['type' => 'string'])]
        ?array $readFromMetadata = null,
        #[Schema(description: 'Metadata tags its value is written into on export', items: ['type' => 'string'])]
        ?array $writeMetadata = null,
        #[Schema(description: 'Technical key, unique in the workspace')]
        ?string $key = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->post('/attribute-definitions', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'policy' => '/attribute-policies/'.$policyId,
            'type' => $type,
            'entityList' => null !== $entityListId ? '/entity-lists/'.$entityListId : null,
            'multiple' => $multiple,
            'translatable' => $translatable,
            'searchable' => $searchable,
            'facetEnabled' => $facetEnabled,
            'sortable' => $sortable,
            'suggest' => $suggest,
            'enabled' => $enabled,
            'editable' => $editable,
            'editableInGui' => $editableInGui,
            'allowInvalid' => $allowInvalid,
            'annotationsEnabled' => $annotationsEnabled,
            'fillFromName' => $fillFromName,
            'initialValues' => $initialValues,
            'fallback' => $fallback,
            'readFromMetadata' => $readFromMetadata,
            'writeMetadata' => $writeMetadata,
            'key' => $key,
            'target' => $target,
            'labels' => $labels,
            'translations' => $translations,
        ]));
    }

    #[McpTool(
        name: 'update_attribute_definition',
        description: 'Update an attribute definition. Only the given fields are changed. Changing the type converts the existing values, or is refused when they cannot be.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updateDefinition(
        #[Schema(description: 'Attribute definition ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: 'ID of the attribute policy controlling who can read and edit it')]
        ?string $policyId = null,
        #[Schema(description: 'Type of the values', enum: self::TYPES)]
        ?string $type = null,
        #[Schema(description: 'ID of the entity list providing the values, for the "entity" type')]
        ?string $entityListId = null,
        ?bool $multiple = null,
        ?bool $translatable = null,
        ?bool $searchable = null,
        ?bool $facetEnabled = null,
        ?bool $sortable = null,
        ?bool $suggest = null,
        ?bool $enabled = null,
        ?bool $editable = null,
        ?bool $editableInGui = null,
        ?bool $allowInvalid = null,
        ?bool $annotationsEnabled = null,
        ?bool $fillFromName = null,
        #[Schema(definition: self::INITIAL_VALUES)]
        ?array $initialValues = null,
        #[Schema(definition: self::FALLBACK)]
        ?array $fallback = null,
        #[Schema(items: ['type' => 'string'])]
        ?array $readFromMetadata = null,
        #[Schema(items: ['type' => 'string'])]
        ?array $writeMetadata = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->patch('/attribute-definitions/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'policy' => null !== $policyId ? '/attribute-policies/'.$policyId : null,
            'type' => $type,
            'entityList' => null !== $entityListId ? '/entity-lists/'.$entityListId : null,
            'multiple' => $multiple,
            'translatable' => $translatable,
            'searchable' => $searchable,
            'facetEnabled' => $facetEnabled,
            'sortable' => $sortable,
            'suggest' => $suggest,
            'enabled' => $enabled,
            'editable' => $editable,
            'editableInGui' => $editableInGui,
            'allowInvalid' => $allowInvalid,
            'annotationsEnabled' => $annotationsEnabled,
            'fillFromName' => $fillFromName,
            'initialValues' => $initialValues,
            'fallback' => $fallback,
            'readFromMetadata' => $readFromMetadata,
            'writeMetadata' => $writeMetadata,
            'target' => $target,
            'labels' => $labels,
            'translations' => $translations,
        ]));
    }

    /**
     * @return array{sorted: list<string>}
     */
    #[McpTool(
        name: 'sort_attribute_definitions',
        description: 'Reorder the attribute definitions of a workspace (display order of the fields).',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function sortDefinitions(
        #[Schema(description: 'Attribute definition IDs in their new order', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/attribute-definitions/sort', $ids);

        return ['sorted' => $ids];
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_attribute_definition',
        description: 'Delete an attribute definition, with its values on every asset. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function deleteDefinition(
        #[Schema(description: 'Attribute definition ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/attribute-definitions/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_attribute_policies',
        description: 'List the attribute policies of a workspace. A policy groups attribute definitions and controls who can read ("public": anyone who can read the asset) and edit ("editable": anyone who can edit the asset) their values; otherwise the users granted on the policy.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listPolicies(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/attribute-policies', [
            'workspaceId' => $workspaceId,
            'page' => $page,
        ]);
    }

    #[McpTool(
        name: 'create_attribute_policy',
        description: 'Create an attribute policy in a workspace. With a "key" already used in the workspace, the existing policy is updated instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createPolicy(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'Whether the values are readable by anyone who can read the asset')]
        bool $public,
        #[Schema(description: 'Whether the values are editable by anyone who can edit the asset')]
        bool $editable,
        #[Schema(description: 'Technical key, unique in the workspace')]
        ?string $key = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
    ): array {
        return $this->client->post('/attribute-policies', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'public' => $public,
            'editable' => $editable,
            'key' => $key,
            'labels' => $labels,
        ]));
    }

    #[McpTool(
        name: 'update_attribute_policy',
        description: 'Update an attribute policy. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updatePolicy(
        #[Schema(description: 'Attribute policy ID (UUID)')]
        string $id,
        ?string $name = null,
        ?bool $public = null,
        ?bool $editable = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
    ): array {
        return $this->client->patch('/attribute-policies/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'public' => $public,
            'editable' => $editable,
            'labels' => $labels,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_attribute_policy',
        description: 'Delete an attribute policy, with its attribute definitions and their values. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function deletePolicy(
        #[Schema(description: 'Attribute policy ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/attribute-policies/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
