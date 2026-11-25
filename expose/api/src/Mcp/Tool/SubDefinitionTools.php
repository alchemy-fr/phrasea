<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Sub-definitions are the renditions of an asset (thumbnail, preview…), each with its own file.
 */
final readonly class SubDefinitionTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_sub_definitions',
        description: 'List the sub-definitions (renditions: thumbnail, preview…) of an asset, with their name, type, size and file URLs (30 per page).',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/assets/'.rawurlencode($assetId).'/sub-definitions', [
            'page' => $page,
        ]);
    }

    #[McpTool(
        name: 'get_sub_definition',
        description: 'Get a sub-definition (rendition) of an asset, with its file URLs.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Sub-definition ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/sub-definitions/'.rawurlencode($id));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_sub_definition',
        description: 'Delete a sub-definition (rendition) of an asset and its file; the asset itself is kept. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Sub-definition ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/sub-definitions/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
