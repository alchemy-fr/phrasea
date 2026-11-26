<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * What can be read about an asset besides its attributes: the versions and the metadata of its file, its
 * duplicates, its audience and its history.
 */
final readonly class AssetInsightTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_file_versions',
        description: 'List the previous versions of the source file of an asset (a version is kept each time the file is replaced), with their name, date and file.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listFileVersions(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/asset-file-versions', [
            'assetId' => $assetId,
            'page' => $page,
        ]);
    }

    #[McpTool(
        name: 'find_duplicate_assets',
        description: 'Find the assets whose file the file analysis detected as a duplicate of the source file of an asset ("duplicates": each asset with the "analyzers" that matched, e.g. same checksum or same image). The assets the user cannot see are left out.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function findDuplicates(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($assetId).'/duplicates');
    }

    #[McpTool(
        name: 'get_file_metadata',
        description: 'Get the technical metadata read from a file (EXIF, IPTC, XMP…, by tag name, e.g. "XMP-dc:Title"), as "metadata". The file of an asset is "source.id" of get_asset; a rendition has its own file.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getFileMetadata(
        #[Schema(description: 'File ID (UUID)')]
        string $fileId,
    ): array {
        return $this->client->get('/files/'.rawurlencode($fileId).'/metadata');
    }

    #[McpTool(
        name: 'get_asset_metrics',
        description: 'Get the audience of an asset (views, or plays for a video or an audio file), as measured by Matomo. Refused when the instance has no Matomo.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getMetrics(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->get('/assets/'.rawurlencode($assetId).'/metrics');
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_audit_logs',
        description: 'List the audit log, the last entry first: who did what on which object (action, actor, object, workspace, data). Administrators only.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listAuditLogs(
        #[Schema(description: 'Only the entries of this object (asset, collection… ID)')]
        ?string $objectId = null,
        #[Schema(description: 'Only the entries of this object type: "asset", "collection", "workspace", "saved_search", "basket", "attribute_definition"…')]
        ?string $objectType = null,
        #[Schema(
            description: 'Only these actions: asset_moved, asset_added_to_collection, asset_removed_from_collection, asset_substituted, asset_attribute_changed, object_created, object_updated, object_deleted, object_trashed, object_restored, collection_moved, asset_quarantine_bypassed, asset_copied, asset_downloaded, rendition_deleted, permission_granted, permission_changed, permission_revoked, workflow_triggered, workflow_job_rerun, workflow_cancelled, automation_triggered, workspace_terms_signed, basket_archived, basket_unarchived, basket_assets_added, basket_assets_removed, profile_updated',
            items: ['type' => 'string'],
        )]
        ?array $actions = null,
        #[Schema(description: 'Only the entries of this user (acting, or impersonated by an administrator)')]
        ?string $userId = null,
        #[Schema(description: 'Only the entries of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Only the entries from this date (ISO 8601)')]
        ?string $after = null,
        #[Schema(description: 'Only the entries until this date (ISO 8601)')]
        ?string $before = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 50,
    ): array {
        return $this->client->getCollection('/audit-logs', ApiClient::withoutNulls([
            'objectId' => $objectId,
            'objectType' => $objectType,
            'action' => null !== $actions ? array_values($actions) : null,
            'userId' => $userId,
            'workspaceId' => $workspaceId,
            'date' => ApiClient::withoutNulls(['after' => $after, 'before' => $before]) ?: null,
            'page' => $page,
            'limit' => $limit,
        ]));
    }
}
