<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class PublicationTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_publications',
        description: 'List the publications visible to the current user (30 per page). By default only root publications are returned; use "parentId" for the children of a publication or "flatten" for every level.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Case-insensitive search on the title')]
        ?string $title = null,
        #[Schema(description: 'Case-insensitive search on the description')]
        ?string $description = null,
        #[Schema(description: 'Free-text search, on the title')]
        ?string $query = null,
        #[Schema(description: 'Only the children of this publication')]
        ?string $parentId = null,
        #[Schema(description: 'Only the publications using this profile')]
        ?string $profileId = null,
        #[Schema(description: 'Return publications of every level, not only roots')]
        bool $flatten = false,
        #[Schema(description: 'Only the publications owned by the current user')]
        bool $mine = false,
        #[Schema(description: 'Only the publications the current user can edit')]
        bool $editable = false,
        #[Schema(description: 'Only the publications whose expiration date has passed')]
        bool $expired = false,
        #[Schema(description: 'Only the publications with no asset')]
        bool $empty = false,
        #[Schema(description: 'Only the disabled publications')]
        bool $disabled = false,
        #[Schema(description: 'Sort order: title (A to Z) or createdAt (newest first)', enum: ['title', 'createdAt'])]
        string $orderBy = 'title',
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        $params = ApiClient::withoutNulls([
            'title' => $title,
            'description' => $description,
            'query' => $query,
            'parentId' => $parentId,
            'profileId' => $profileId,
            'flatten' => $flatten ? 'true' : null,
            'mine' => $mine ? 'true' : null,
            'editable' => $editable ? 'true' : null,
            'expired' => $expired ? 'true' : null,
            'empty' => $empty ? 'true' : null,
            'disabled' => $disabled ? 'true' : null,
            'page' => $page,
        ]);
        $params['order'] = [$orderBy => 'title' === $orderBy ? 'asc' : 'desc'];

        $result = $this->client->getCollection('/publications', $params);
        // Only computed when reading a single publication: always false in a list, which would mislead.
        $result['items'] = array_map(static function (array $publication): array {
            unset($publication['authorized']);

            return $publication;
        }, $result['items']);

        return $result;
    }

    #[McpTool(
        name: 'get_publication',
        description: 'Get a publication with its settings, assets and children.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Publication ID (UUID) or slug')]
        string $id,
    ): array {
        return $this->client->get('/publications/'.rawurlencode($id));
    }

    /**
     * @return array{slug: string, available: bool}
     */
    #[McpTool(
        name: 'check_publication_slug',
        description: 'Tell whether a URL slug is still available for a publication. Requires the right to create publications.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function checkSlug(string $slug): array
    {
        return [
            'slug' => $slug,
            'available' => true === $this->client->get('/publications/slug-availability/'.rawurlencode($slug)),
        ];
    }

    #[McpTool(
        name: 'create_publication',
        description: 'Create a publication. Unset settings are inherited from the profile, if any.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $title,
        ?string $description = null,
        #[Schema(description: 'URL slug, must be unique', maxLength: 100)]
        ?string $slug = null,
        #[Schema(description: 'ID of the profile to inherit settings from')]
        ?string $profileId = null,
        #[Schema(description: 'ID of the parent publication, to create a sub-publication')]
        ?string $parentId = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(definition: ToolSchemas::CONFIG)]
        ?array $config = null,
    ): array {
        return $this->client->post('/publications', ApiClient::withoutNulls([
            'title' => $title,
            'description' => $description,
            'slug' => $slug,
            'profile' => self::iri('/publication-profiles/', $profileId),
            'parent' => self::iri('/publications/', $parentId),
            'translations' => $translations,
            'config' => $config,
        ]));
    }

    #[McpTool(
        name: 'update_publication',
        description: 'Update a publication. Only the given fields are changed; "config" keys are merged into the current settings.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Publication ID (UUID)')]
        string $id,
        ?string $title = null,
        ?string $description = null,
        #[Schema(maxLength: 100)]
        ?string $slug = null,
        #[Schema(description: 'ID of the profile to inherit settings from')]
        ?string $profileId = null,
        #[Schema(description: 'Detach the publication from its profile (overrides "profileId")')]
        bool $removeProfile = false,
        #[Schema(description: 'ID of the new parent publication')]
        ?string $parentId = null,
        #[Schema(description: 'Make the publication a root publication, with no parent (overrides "parentId")')]
        bool $moveToRoot = false,
        #[Schema(description: 'ID of the asset shown as cover; it must be an asset of this publication (default: the first asset)')]
        ?string $coverAssetId = null,
        #[Schema(description: 'ID of the asset offered as the download package of the publication; it must be an asset of this publication')]
        ?string $packageAssetId = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
        #[Schema(definition: ToolSchemas::CONFIG)]
        ?array $config = null,
    ): array {
        $body = ApiClient::withoutNulls([
            'title' => $title,
            'description' => $description,
            'slug' => $slug,
            'profile' => self::iri('/publication-profiles/', $profileId),
            'parent' => self::iri('/publications/', $parentId),
            'cover' => self::iri('/assets/', $coverAssetId),
            'package' => self::iri('/assets/', $packageAssetId),
            'translations' => $translations,
            'config' => $config,
        ]);
        // A null value is an explicit change in a merge patch.
        if ($removeProfile) {
            $body['profile'] = null;
        }
        if ($moveToRoot) {
            $body['parent'] = null;
        }

        return $this->client->patch('/publications/'.rawurlencode($id), $body);
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_publication',
        description: 'Delete a publication, with its assets and sub-publications. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Publication ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/publications/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    #[McpTool(
        name: 'sort_publication_assets',
        description: 'Reorder the assets of a publication.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function sortAssets(
        #[Schema(description: 'Publication ID (UUID)')]
        string $id,
        #[Schema(description: 'Asset IDs in their new order', items: ['type' => 'string'], minItems: 1)]
        array $assetIds,
    ): array {
        return $this->client->post('/publications/'.rawurlencode($id).'/sort-assets', [
            'order' => array_values($assetIds),
        ]);
    }

    private static function iri(string $prefix, ?string $id): ?string
    {
        return null !== $id ? $prefix.rawurlencode($id) : null;
    }
}
