<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class WorkspaceTools
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_workspaces',
        description: 'List the workspaces the current user can access, by name.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Contains match on the name or any translation of it')]
        ?string $query = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/workspaces', ApiClient::withoutNulls([
            'query' => $query,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_workspace',
        description: 'Get a workspace with its settings (locales, trash retention, default asset status, terms…).',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Workspace ID (UUID) or slug')]
        string $id,
    ): array {
        if (1 === preg_match(self::UUID_PATTERN, $id)) {
            return $this->client->get('/workspaces/'.rawurlencode($id));
        }

        return $this->client->get('/workspaces-by-slug/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_workspace',
        description: 'Create a workspace, owned by the current user. It starts empty: add its attribute and rendition policies and definitions afterwards.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $name,
        #[Schema(description: 'Unique URL slug')]
        string $slug,
        #[Schema(description: 'Locales of the translatable attributes, e.g. ["en", "fr"]', items: ['type' => 'string'])]
        ?array $enabledLocales = null,
        #[Schema(description: 'Locales used, in this order, when a value is missing in the requested locale', items: ['type' => 'string'])]
        ?array $localeFallbacks = null,
        #[Schema(description: 'Whether anonymous users can see the public items of the workspace')]
        ?bool $public = null,
        #[Schema(description: 'Days the deleted assets and collections stay in the trash (0 to 730)', minimum: 0, maximum: 730)]
        ?int $trashRetentionDelay = null,
        #[Schema(description: 'Status of the new assets: 0 = accepted, 1 = pending analysis, 2 = quarantined', enum: [0, 1, 2])]
        ?int $assetDefaultStatus = null,
        #[Schema(description: 'Whether the files must be analyzed before the assets are accepted')]
        ?bool $fileAnalysisRequired = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(description: 'Terms the users must accept before accessing the workspace (HTML allowed)')]
        ?string $terms = null,
        #[Schema(type: 'object', description: 'Translations of the terms by locale, e.g. {"fr": "…"}', additionalProperties: ['type' => 'string'])]
        ?array $termsTranslations = null,
        #[Schema(description: 'Whether the terms are attached to the exports')]
        ?bool $attachTermsToExports = null,
    ): array {
        return $this->client->post('/workspaces', ApiClient::withoutNulls([
            'name' => $name,
            'slug' => $slug,
            'enabledLocales' => $enabledLocales,
            'localeFallbacks' => $localeFallbacks,
            'public' => $public,
            'trashRetentionDelay' => $trashRetentionDelay,
            'assetDefaultStatus' => $assetDefaultStatus,
            'fileAnalysisRequired' => $fileAnalysisRequired,
            'translations' => $translations,
            'terms' => $terms,
            'termsTranslations' => $termsTranslations,
            'attachTermsToExports' => $attachTermsToExports,
        ]));
    }

    #[McpTool(
        name: 'update_workspace',
        description: 'Update the settings of a workspace. Only the given fields are changed; an empty "terms" removes the terms.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: 'Unique URL slug')]
        ?string $slug = null,
        #[Schema(description: 'Locales of the translatable attributes, e.g. ["en", "fr"]', items: ['type' => 'string'])]
        ?array $enabledLocales = null,
        #[Schema(description: 'Locales used, in this order, when a value is missing in the requested locale', items: ['type' => 'string'])]
        ?array $localeFallbacks = null,
        #[Schema(description: 'Whether anonymous users can see the public items of the workspace')]
        ?bool $public = null,
        #[Schema(description: 'Days the deleted assets and collections stay in the trash (0 to 730)', minimum: 0, maximum: 730)]
        ?int $trashRetentionDelay = null,
        #[Schema(description: 'Status of the new assets: 0 = accepted, 1 = pending analysis, 2 = quarantined', enum: [0, 1, 2])]
        ?int $assetDefaultStatus = null,
        #[Schema(description: 'Whether the files must be analyzed before the assets are accepted')]
        ?bool $fileAnalysisRequired = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(description: 'Terms the users must accept before accessing the workspace (HTML allowed), "" to remove them')]
        ?string $terms = null,
        #[Schema(type: 'object', description: 'Translations of the terms by locale, e.g. {"fr": "…"}', additionalProperties: ['type' => 'string'])]
        ?array $termsTranslations = null,
        #[Schema(description: 'Whether the terms are attached to the exports')]
        ?bool $attachTermsToExports = null,
    ): array {
        return $this->client->patch('/workspaces/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'slug' => $slug,
            'enabledLocales' => $enabledLocales,
            'localeFallbacks' => $localeFallbacks,
            'public' => $public,
            'trashRetentionDelay' => $trashRetentionDelay,
            'assetDefaultStatus' => $assetDefaultStatus,
            'fileAnalysisRequired' => $fileAnalysisRequired,
            'translations' => $translations,
            'terms' => $terms,
            'termsTranslations' => $termsTranslations,
            'attachTermsToExports' => $attachTermsToExports,
        ]));
    }
}
