<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Upload links are secret URLs letting someone without an account upload to one target, within limits.
 */
final readonly class UploadLinkTools
{
    /**
     * The limits of a link that update_upload_link can remove.
     */
    private const array CLEARABLE_FIELDS = ['name', 'maxAssets', 'maxTotalSize'];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_upload_links',
        description: 'List the upload links the current user created (every link for an admin), the newest first (30 per page), with their token, limits, counters and status (active, disabled, expired or exhausted). Requires the permission to create upload links.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the links of this target (ID)')]
        ?string $targetId = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/upload-links', ApiClient::withoutNulls([
            'target' => $targetId,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'get_upload_link',
        description: 'Get an upload link: its target, token, limits, counters (usedAssets, usedSize in bytes), last commit date and status. The URL to hand out is the Uploader client URL followed by "/l/<token>".',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Upload link ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/upload-links/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_upload_link',
        description: 'Create an upload link to a target the current user can upload to: whoever holds its URL (the Uploader client URL followed by "/l/<token>") can upload files to the target on behalf of the current user, until it expires (within the maximum lifetime of the links, 90 days by default) and within its limits. Requires the permission to create upload links.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Target ID (UUID)')]
        string $targetId,
        #[Schema(description: 'Expiration date (ISO 8601)', format: 'date-time')]
        string $expiresAt,
        #[Schema(description: 'Name shown to its holders', maxLength: 255)]
        ?string $name = null,
        #[Schema(description: 'Maximum number of files sent through the link, over all its commits; unset: no limit', minimum: 1)]
        ?int $maxAssets = null,
        #[Schema(description: 'Maximum total size in bytes of the files sent through the link; unset: no limit', minimum: 1)]
        ?int $maxTotalSize = null,
        #[Schema(description: 'false creates the link disabled')]
        ?bool $enabled = null,
    ): array {
        return $this->client->post('/upload-links', ApiClient::withoutNulls([
            'target' => '/targets/'.rawurlencode($targetId),
            'name' => $name,
            'expiresAt' => $expiresAt,
            'maxAssets' => $maxAssets,
            'maxTotalSize' => $maxTotalSize,
            'enabled' => $enabled,
        ]));
    }

    #[McpTool(
        name: 'update_upload_link',
        description: 'Update an upload link: only the given fields change, and the ones listed in "clear" are removed (no name, no limit). "enabled": false revokes the link until it is enabled again; its creator may always disable it, editing anything else requires the permission to create upload links.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Upload link ID (UUID)')]
        string $id,
        #[Schema(maxLength: 255)]
        ?string $name = null,
        ?bool $enabled = null,
        #[Schema(description: 'Expiration date (ISO 8601)', format: 'date-time')]
        ?string $expiresAt = null,
        #[Schema(minimum: 1)]
        ?int $maxAssets = null,
        #[Schema(description: 'In bytes', minimum: 1)]
        ?int $maxTotalSize = null,
        #[Schema(description: 'Fields to remove', items: ['type' => 'string', 'enum' => self::CLEARABLE_FIELDS])]
        ?array $clear = null,
    ): array {
        $body = ApiClient::withoutNulls([
            'name' => $name,
            'enabled' => $enabled,
            'expiresAt' => $expiresAt,
            'maxAssets' => $maxAssets,
            'maxTotalSize' => $maxTotalSize,
        ]);
        foreach (array_intersect(self::CLEARABLE_FIELDS, $clear ?? []) as $field) {
            $body[$field] = null;
        }

        return $this->client->patch('/upload-links/'.rawurlencode($id), $body);
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_upload_link',
        description: 'Delete an upload link: its URL stops working for good; the commits and files sent with it stay. To revoke it for a while, disable it with update_upload_link instead.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Upload link ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/upload-links/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    #[McpTool(
        name: 'regenerate_upload_link_token',
        description: 'Give an upload link a new token: the URL handed out so far stops working at once, the files sent with it stay. Returns the link with its new token.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false),
    )]
    public function regenerateToken(
        #[Schema(description: 'Upload link ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/upload-links/'.rawurlencode($id).'/regenerate-token');
    }
}
