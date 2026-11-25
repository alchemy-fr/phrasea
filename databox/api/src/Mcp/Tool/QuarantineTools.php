<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * An asset whose file analysis failed (virus, duplicate of an existing file…) is quarantined until a user resolves it.
 */
final readonly class QuarantineTools
{
    private const array ITEMS = [
        'type' => 'array',
        'minItems' => 1,
        'maxItems' => 100,
        'description' => 'The quarantined assets to resolve',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'ID of the quarantined asset'],
                'targetId' => ['type' => 'string', 'description' => '"merge" with addAsVersion only: ID of the duplicate receiving the file as a new version, needed when the asset has several valid targets'],
            ],
            'required' => ['id'],
        ],
    ];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    #[McpTool(
        name: 'check_quarantined_assets',
        description: 'Tell, for each quarantined asset, which action of resolve_quarantined_assets it is eligible to ("bypass", "trash", "delete", "mergeAsVersion", "mergeDiscard"), why not otherwise ("reasons"), and the existing assets it duplicates ("duplicates", with "validTarget" for a merge as a new version). Changes nothing. Find the quarantined assets with search_assets and the condition `@assetStatus IS 2`.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function check(
        #[Schema(description: 'IDs of the quarantined assets', items: ['type' => 'string'], minItems: 1, maxItems: 100)]
        array $ids,
    ): array {
        return $this->client->post('/assets/quarantine/prepare-batch', [
            'ids' => array_values($ids),
        ]);
    }

    #[McpTool(
        name: 'resolve_quarantined_assets',
        description: 'Resolve quarantined assets with the same action, asset by asset: "bypass" accepts them despite their failed analysis, "merge" adds their file as a new version of the asset they duplicate (or discards it, with addAsVersion false) then removes them, "trash" moves them to the trash, "delete" deletes them for good. A no longer eligible asset is skipped with the reason; the result tells the outcome of each one. Check them first with check_quarantined_assets.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false),
    )]
    public function resolve(
        #[Schema(enum: ['bypass', 'merge', 'trash', 'delete'])]
        string $action,
        #[Schema(definition: self::ITEMS)]
        array $items,
        #[Schema(description: '"merge" only: keep the file as a new version of the duplicate (true) or discard it (false); the values of the duplicate are kept either way')]
        bool $addAsVersion = true,
    ): array {
        return $this->client->post('/assets/quarantine/batch', [
            'action' => $action,
            'items' => array_map(static fn (array $item): array => ApiClient::withoutNulls([
                'id' => $item['id'] ?? null,
                'targetId' => $item['targetId'] ?? null,
            ]), array_values($items)),
            'addAsVersion' => $addAsVersion,
        ]);
    }

    #[McpTool(
        name: 'bypass_quarantine',
        description: 'Accept a quarantined asset despite its failed file analysis: it leaves the quarantine, the analysis being marked as bypassed (its results are kept).',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function bypass(
        #[Schema(description: 'Asset ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/assets/'.rawurlencode($id).'/quarantine-bypass');
    }
}
