<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * A story is an asset grouping other assets, in an order, one of them giving its cover. A story is created with
 * create_asset_from_url (isStory) and filled with add_assets_to_collection (storyId).
 */
final readonly class StoryTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_story_assets',
        description: 'List the assets of a story in their order: each item gives the asset and its "position" (from 0). The assets the user cannot see are left out.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listAssets(
        #[Schema(description: 'Story ID (the asset ID of the story)')]
        string $storyId,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 50)]
        int $limit = 20,
    ): array {
        return $this->client->getCollection('/assets/'.rawurlencode($storyId).'/story-assets', [
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[McpTool(
        name: 'set_story_cover',
        description: 'Make one of the assets of a story its cover: the source file of that asset becomes the thumbnail of the story. Returns the story.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function setCover(
        #[Schema(description: 'Story ID (the asset ID of the story)')]
        string $storyId,
        #[Schema(description: 'ID of the asset of the story becoming its cover')]
        string $assetId,
    ): array {
        return $this->client->put('/assets/'.rawurlencode($storyId).'/story-cover', [
            'assetId' => $assetId,
        ]);
    }

    /**
     * @return array{moved: string}
     */
    #[McpTool(
        name: 'set_asset_position',
        description: 'Move an asset to a rank inside a story or a collection, shifting the assets in between; a rank beyond the last one moves it to the end. Give the story or the collection.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function setPosition(
        #[Schema(description: 'ID of the asset to move')]
        string $assetId,
        #[Schema(description: 'Rank to move the asset to, from 0', minimum: 0)]
        int $position,
        #[Schema(description: 'ID of the story the asset belongs to')]
        ?string $storyId = null,
        #[Schema(description: 'ID of the collection the asset belongs to, instead of storyId')]
        ?string $collectionId = null,
    ): array {
        $this->client->put('/assets/'.rawurlencode($assetId).'/position', [
            'destination' => null !== $collectionId ? '/collections/'.$collectionId : '/assets/'.$storyId,
            'position' => $position,
        ]);

        return ['moved' => $assetId];
    }
}
