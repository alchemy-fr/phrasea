<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * An attachment links another asset of the same workspace to an asset (a contract, a making-of, a subtitle
 * file…), shown with it.
 */
final readonly class AttachmentTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_attachments',
        description: 'List the attachments of an asset, the highest priority first: each one has its own ID, a name and the attached asset.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->getCollection('/attachments', ['assetId' => $assetId]);
    }

    #[McpTool(
        name: 'create_attachment',
        description: 'Attach an existing asset of the same workspace to an asset. To attach a new file, create its asset first with create_asset_from_url.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'ID of the asset receiving the attachment')]
        string $assetId,
        #[Schema(description: 'ID of the asset to attach')]
        string $attachmentId,
        #[Schema(description: 'Label of the attachment')]
        ?string $name = null,
        #[Schema(description: 'Order of the attachments: the highest first')]
        ?int $priority = null,
    ): array {
        return $this->client->post('/attachments', ApiClient::withoutNulls([
            'assetId' => $assetId,
            'attachmentId' => $attachmentId,
            'name' => $name,
            'priority' => $priority,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_attachment',
        description: 'Remove an attachment from an asset, by the ID of the attachment (see list_attachments). The attached asset is kept.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Attachment ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/attachments/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
