<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Publication profiles hold settings shared by several publications.
 */
final readonly class ProfileTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_profiles',
        description: 'List the publication profiles (30 per page). A profile holds settings inherited by the publications using it.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Case-insensitive search on the name')]
        ?string $name = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/publication-profiles', array_filter([
            'name' => $name,
            'order' => ['name' => 'asc'],
            'page' => $page,
        ], static fn (mixed $v): bool => null !== $v));
    }

    #[McpTool(
        name: 'get_profile',
        description: 'Get a publication profile with its settings.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Profile ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/publication-profiles/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_profile',
        description: 'Create a publication profile.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $name,
        #[Schema(definition: ToolSchemas::CONFIG)]
        ?array $config = null,
    ): array {
        return $this->client->post('/publication-profiles', array_filter([
            'name' => $name,
            'config' => $config,
        ], static fn (mixed $v): bool => null !== $v));
    }

    #[McpTool(
        name: 'update_profile',
        description: 'Update a publication profile. Only the given fields are changed; "config" keys are merged into the current settings. Changes apply to every publication using the profile.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Profile ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(definition: ToolSchemas::CONFIG)]
        ?array $config = null,
    ): array {
        return $this->client->put('/publication-profiles/'.rawurlencode($id), array_filter([
            'name' => $name,
            'config' => $config,
        ], static fn (mixed $v): bool => null !== $v));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_profile',
        description: 'Delete a publication profile. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Profile ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/publication-profiles/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
