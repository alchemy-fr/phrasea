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
        description: 'Get an uploaded file (asset) of a commit: original name, MIME type, size, a temporary download URL and acknowledgement state. Requires the admin role: other users get a 403 (the targets read their files with the token of the commit). Take the asset IDs from get_commit.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getAsset(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($id));
    }

    /**
     * @return array{result: true}
     */
    #[McpTool(
        name: 'acknowledge_commit',
        description: 'Acknowledge a commit and all its files on behalf of its target, as the target does once it has read them: unblocks a commit the target consumed but left pending (e.g. after a failed acknowledgement). The files are then deleted from the Uploader storage and the sender is notified if they asked for it: only acknowledge a commit its target already ingested. Processed in the background. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function acknowledge(
        #[Schema(description: 'Commit ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/commits/'.rawurlencode($id).'/ack');
    }

    /**
     * @return array{result: true}
     */
    #[McpTool(
        name: 'acknowledge_upload_asset',
        description: 'Acknowledge one file of a commit on behalf of its target: acknowledging the last pending file acknowledges the whole commit, whose files are then deleted from the Uploader storage. Unblocks a commit whose target ingested a file without acknowledging it. Processed in the background. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function acknowledgeAsset(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/assets/'.rawurlencode($id).'/ack');
    }
}
