<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * An export packs renditions of assets into an archive to download, built in the background. An import creates
 * the assets described by an instructions file (JSON or NDJSON), in the background too.
 */
final readonly class ExportImportTools
{
    private const string EXPORT_STATUS = '"status": 0 = pending, 1 = in progress, 2 = ready ("downloadUrl" is then the temporary URL of the archive), 3 = failed';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    #[McpTool(
        name: 'create_asset_export',
        description: 'Export renditions of assets as an archive to download. The archive is built in the background: the result is the export, pending; call get_asset_export with its ID until it is ready, then give its "downloadUrl" to the user. '.self::EXPORT_STATUS.'.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createExport(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $assetIds,
        #[Schema(description: 'IDs of the rendition definitions to export (list_rendition_definitions)', items: ['type' => 'string'], minItems: 1)]
        array $renditionDefinitionIds,
        #[Schema(description: 'Export the whole files, ignoring the regions of interest of the assets')]
        bool $ignoreRoi = false,
    ): array {
        return $this->client->post('/asset-exports', [
            'assets' => array_values($assetIds),
            'renditions' => array_values($renditionDefinitionIds),
            'ignoreRoi' => $ignoreRoi,
        ]);
    }

    #[McpTool(
        name: 'get_asset_export',
        description: 'Get an export of the current user, to follow it: '.self::EXPORT_STATUS.'.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getExport(
        #[Schema(description: 'Export ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/asset-exports/'.rawurlencode($id));
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_imports',
        description: 'List the asset imports of a workspace, the last one first: status, counts of the created, skipped (key already used) and failed entries, error. The failed entries are listed by list_asset_import_errors.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listImports(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/asset-imports', [
            'workspace' => $workspaceId,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_import_errors',
        description: 'List the entries of an import that could not be imported, by line (NDJSON) or position (JSON), with the error.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listImportErrors(
        #[Schema(description: 'Import ID (UUID)')]
        string $importId,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/asset-imports/'.rawurlencode($importId).'/errors', [
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
