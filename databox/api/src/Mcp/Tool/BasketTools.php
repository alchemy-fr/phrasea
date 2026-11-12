<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class BasketTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_baskets',
        description: 'List the baskets (personal selections of assets, across workspaces) of the current user, by name.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Search on the name and description')]
        ?string $query = null,
        #[Schema(description: 'List the archived baskets instead of the active ones')]
        bool $archived = false,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/baskets', ApiClient::withoutNulls([
            'query' => $query,
            'archived' => $archived ? 'true' : null,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'create_basket',
        description: 'Create a basket.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $name,
        ?string $description = null,
    ): array {
        return $this->client->post('/baskets', ApiClient::withoutNulls([
            'name' => $name,
            'description' => $description,
        ]));
    }

    #[McpTool(
        name: 'update_basket',
        description: 'Rename a basket or change its description. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Basket ID (UUID)')]
        string $id,
        ?string $name = null,
        ?string $description = null,
    ): array {
        return $this->client->patch('/baskets/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'description' => $description,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_basket',
        description: 'Delete a basket. Its assets are kept. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Basket ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/baskets/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    #[McpTool(
        name: 'add_to_basket',
        description: 'Add assets to a basket, or to the default basket of the current user (created if needed) when no basket is given.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function add(
        #[Schema(description: 'Asset IDs (UUID)', items: ['type' => 'string'], minItems: 1)]
        array $assetIds,
        #[Schema(description: 'Basket ID (UUID); the default basket when unset')]
        ?string $basketId = null,
    ): array {
        return $this->client->post(
            '/baskets/'.(null !== $basketId ? rawurlencode($basketId) : 'default').'/assets',
            ['assets' => array_map(static fn (string $id): array => ['id' => $id], array_values($assetIds))],
        );
    }

    #[McpTool(
        name: 'remove_from_basket',
        description: 'Remove items from a basket. Items are identified by their own ID (see list_basket_assets), not by the asset ID.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function remove(
        #[Schema(description: 'Basket ID (UUID)')]
        string $basketId,
        #[Schema(description: 'IDs of the basket items', items: ['type' => 'string'], minItems: 1)]
        array $itemIds,
    ): array {
        return $this->client->post('/baskets/'.rawurlencode($basketId).'/remove', [
            'items' => array_values($itemIds),
        ]);
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_basket_assets',
        description: 'List the items of a basket, in their order: each item has its own ID and the asset it holds.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listAssets(
        #[Schema(description: 'Basket ID (UUID)')]
        string $basketId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/baskets/'.rawurlencode($basketId).'/assets', ['page' => $page]);
    }
}
