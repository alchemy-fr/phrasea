<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Share links open a publication to whoever holds them, whatever its visibility and security method.
 */
final readonly class ShareTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_publication_shares',
        description: 'List the share links of a publication (all at once, not paginated), with their URL and status (active, expired or revoked). Requires the operator right on the publication.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Publication ID (UUID)')]
        string $publicationId,
    ): array {
        return $this->client->getCollection('/publications/'.rawurlencode($publicationId).'/shares');
    }

    #[McpTool(
        name: 'get_publication_share',
        description: 'Get a share link of a publication.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/publication-shares/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_publication_share',
        description: 'Create a share link: its "url" opens the publication (not its sub-publications) without password nor sign-in, even when the publication is disabled or not visible yet. Requires the operator right on the publication.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Publication ID (UUID)')]
        string $publicationId,
        #[Schema(description: 'Name of the link, e.g. who it is sent to', maxLength: 255)]
        ?string $name = null,
        #[Schema(description: 'The link works until this date (ISO 8601, in the future); forever when unset', format: 'date-time')]
        ?string $expiresAt = null,
    ): array {
        return $this->client->post('/publications/'.rawurlencode($publicationId).'/shares', ApiClient::withoutNulls([
            'name' => $name,
            'expiresAt' => $expiresAt,
        ]));
    }

    #[McpTool(
        name: 'update_publication_share',
        description: 'Rename a share link or change its expiration. Only the given fields are changed; a revoked link cannot be modified.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
        #[Schema(maxLength: 255)]
        ?string $name = null,
        #[Schema(description: 'New expiration date (ISO 8601, in the future)', format: 'date-time')]
        ?string $expiresAt = null,
        #[Schema(description: 'Make the link work forever (overrides "expiresAt")')]
        bool $removeExpiration = false,
    ): array {
        $body = ApiClient::withoutNulls([
            'name' => $name,
            'expiresAt' => $expiresAt,
        ]);
        // A null value is an explicit change in a merge patch.
        if ($removeExpiration) {
            $body['expiresAt'] = null;
        }

        return $this->client->patch('/publication-shares/'.rawurlencode($id), $body);
    }

    #[McpTool(
        name: 'revoke_publication_share',
        description: 'Revoke a share link: it stops working for good but stays listed, with who revoked it and when. Revoking it again changes nothing.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function revoke(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/publication-shares/'.rawurlencode($id).'/revoke');
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_publication_share',
        description: 'Delete a share link: it stops working at once and leaves no trace (prefer revoke_publication_share to keep the history). This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Share ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/publication-shares/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
