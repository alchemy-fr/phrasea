<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class ShareTools
{
    private const string RENDITIONS = 'IDs of the rendition definitions delivered by the link (their policy must be public); every public rendition when unset';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_shares',
        description: 'List the share links of an asset that the current user manages, with their token and alternate URLs.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->getCollection('/shares', ['assetId' => $assetId]);
    }

    #[McpTool(
        name: 'create_share',
        description: 'Create a public link giving access to assets of one workspace, without signing in.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'IDs of the shared assets, all from the same workspace', items: ['type' => 'string'], minItems: 1)]
        array $assetIds,
        ?string $name = null,
        #[Schema(description: 'Whether the link works')]
        ?bool $enabled = null,
        #[Schema(description: 'The link works from this date (ISO 8601)', format: 'date-time')]
        ?string $startsAt = null,
        #[Schema(description: 'The link works until this date (ISO 8601)', format: 'date-time')]
        ?string $expiresAt = null,
        #[Schema(description: self::RENDITIONS, items: ['type' => 'string'])]
        ?array $renditionDefinitionIds = null,
    ): array {
        return $this->client->post('/shares', ApiClient::withoutNulls([
            'assets' => self::iris('/assets/', $assetIds),
            'name' => $name,
            'enabled' => $enabled,
            'startsAt' => $startsAt,
            'expiresAt' => $expiresAt,
            'renditionDefinitions' => null !== $renditionDefinitionIds ? self::iris('/rendition-definitions/', $renditionDefinitionIds) : null,
        ]));
    }

    #[McpTool(
        name: 'update_share',
        description: 'Update a share link. Only the given fields are changed; "assetIds" and "renditionDefinitionIds" replace the current lists.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
        #[Schema(description: 'IDs of the shared assets, all from the same workspace', items: ['type' => 'string'], minItems: 1)]
        ?array $assetIds = null,
        ?string $name = null,
        #[Schema(description: 'Whether the link works')]
        ?bool $enabled = null,
        #[Schema(description: 'The link works from this date (ISO 8601)', format: 'date-time')]
        ?string $startsAt = null,
        #[Schema(description: 'The link works until this date (ISO 8601)', format: 'date-time')]
        ?string $expiresAt = null,
        #[Schema(description: self::RENDITIONS, items: ['type' => 'string'])]
        ?array $renditionDefinitionIds = null,
    ): array {
        return $this->client->patch('/shares/'.rawurlencode($id), ApiClient::withoutNulls([
            'assets' => null !== $assetIds ? self::iris('/assets/', $assetIds) : null,
            'name' => $name,
            'enabled' => $enabled,
            'startsAt' => $startsAt,
            'expiresAt' => $expiresAt,
            'renditionDefinitions' => null !== $renditionDefinitionIds ? self::iris('/rendition-definitions/', $renditionDefinitionIds) : null,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_share',
        description: 'Delete a share link: it stops working at once. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/shares/'.rawurlencode($id));

        return ['deleted' => $id];
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
}
