<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Commits are the batches of files sent to a target, with their form data.
 */
final readonly class CommitTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_commits',
        description: 'List the commits (batches of uploaded files with their form data), the pending ones first, then by date (30 per page). Requires the "commit:list" scope or the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the commits sent to this target (ID)')]
        ?string $targetId = null,
        #[Schema(description: 'Only the commits the target has acknowledged (true) or not yet (false)')]
        ?bool $acknowledged = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/commits', ApiClient::withoutNulls([
            'target' => $targetId,
            'acknowledged' => null !== $acknowledged ? ($acknowledged ? 'true' : 'false') : null,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'get_commit',
        description: 'Get a commit with its files, form data, options and acknowledgement state. Requires the "commit:list" scope or the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Commit ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/commits/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'get_upload_asset',
        description: 'Get an uploaded file (asset) of a commit: original name, MIME type, size, a temporary download URL and acknowledgement state. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getAsset(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($id));
    }
}
