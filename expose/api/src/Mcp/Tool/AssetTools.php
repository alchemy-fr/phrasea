<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class AssetTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_publication_assets',
        description: 'List the assets of a publication in their display order (30 per page).',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Publication ID (UUID)')]
        string $publicationId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/publications/'.rawurlencode($publicationId).'/assets', [
            'order' => ['position' => 'asc', 'createdAt' => 'asc'],
            'page' => $page,
        ]);
    }

    #[McpTool(
        name: 'get_asset',
        description: 'Get an asset with its metadata, file URLs and sub-definitions (renditions).',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'update_asset',
        description: 'Update the metadata of an asset. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
        ?string $title = null,
        ?string $description = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(description: 'Latitude', minimum: -90, maximum: 90)]
        ?float $lat = null,
        #[Schema(description: 'Longitude', minimum: -180, maximum: 180)]
        ?float $lng = null,
    ): array {
        return $this->client->put('/assets/'.rawurlencode($id), array_filter([
            'title' => $title,
            'description' => $description,
            'translations' => $translations,
            'lat' => $lat,
            'lng' => $lng,
        ], static fn (mixed $v): bool => null !== $v));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_asset',
        description: 'Remove an asset from its publication and delete its files. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/assets/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
