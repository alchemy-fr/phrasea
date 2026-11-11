<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class RenditionTools
{
    private const string BUILD_MODE = 'How the rendition file is obtained: 0 = none (uploaded or substituted only), 1 = the source file as is, 2 = built from the YAML build configuration of "definition"';

    private const string DEFINITION = 'YAML build configuration, used with buildMode 2: one section per file family (image, video, audio, document…) listing "transformations" of modules with their options. Call get_rendition_build_reference first for the modules and options.';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    #[McpTool(
        name: 'get_rendition_build_reference',
        description: 'Get the reference of the YAML build configuration of the rendition definitions: the global structure ("reference") then one section per transformation module ("references"). Read it before writing a "definition".',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getBuildReference(): array
    {
        return $this->client->get('/rendition-build-reference');
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_rendition_definitions',
        description: 'List the rendition definitions (the renditions computed for each asset: thumbnail, preview…) of the workspaces visible to the current user, by priority.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listDefinitions(
        #[Schema(description: 'Only the definitions of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Contains match on the name or any translation of it')]
        ?string $query = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/rendition-definitions', ApiClient::withoutNulls([
            'workspaceId' => $workspaceId,
            'query' => $query,
            'target' => $target,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_rendition_definition',
        description: 'Get a rendition definition, with its build configuration.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getDefinition(
        #[Schema(description: 'Rendition definition ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/rendition-definitions/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_rendition_definition',
        description: 'Create a rendition definition in a workspace. With a "key" already used in the workspace, the existing definition is updated instead. An invalid build configuration is refused with the reason.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createDefinition(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'ID of the rendition policy controlling who can see it')]
        string $policyId,
        #[Schema(description: self::BUILD_MODE, enum: [0, 1, 2])]
        ?int $buildMode = null,
        #[Schema(description: self::DEFINITION)]
        ?string $definition = null,
        #[Schema(description: 'ID of the rendition definition whose file is the source of this one (the asset source file when unset)')]
        ?string $parentId = null,
        #[Schema(description: 'Technical key, unique in the workspace')]
        ?string $key = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(description: 'Higher first in the lists')]
        ?int $priority = null,
        #[Schema(description: 'Whether this rendition is the main file of the asset')]
        ?bool $useAsMain = null,
        #[Schema(description: 'Whether this rendition is used as preview')]
        ?bool $useAsPreview = null,
        #[Schema(description: 'Whether this rendition is used as thumbnail')]
        ?bool $useAsThumbnail = null,
        #[Schema(description: 'Whether this rendition is used as animated thumbnail')]
        ?bool $useAsAnimatedThumbnail = null,
        #[Schema(description: 'Whether the rendition can be downloaded')]
        ?bool $download = null,
        #[Schema(description: 'Whether a user can replace the rendition file')]
        ?bool $substitutable = null,
        #[Schema(description: 'Whether the attribute values are embedded into the file on export')]
        ?bool $writeMetadata = null,
        #[Schema(type: 'object', description: 'Metadata written into the file on export, by tag, e.g. {"IPTC:CopyrightNotice": "ACME"}', additionalProperties: ['type' => 'string'])]
        ?array $metadata = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        return $this->client->post('/rendition-definitions', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'policy' => '/rendition-policies/'.$policyId,
            'buildMode' => $buildMode,
            'definition' => $definition,
            'parent' => null !== $parentId ? '/rendition-definitions/'.$parentId : null,
            'key' => $key,
            'target' => $target,
            'priority' => $priority,
            'useAsMain' => $useAsMain,
            'useAsPreview' => $useAsPreview,
            'useAsThumbnail' => $useAsThumbnail,
            'useAsAnimatedThumbnail' => $useAsAnimatedThumbnail,
            'download' => $download,
            'substitutable' => $substitutable,
            'writeMetadata' => $writeMetadata,
            'metadata' => $metadata,
            'labels' => $labels,
            'translations' => $translations,
        ]));
    }

    #[McpTool(
        name: 'update_rendition_definition',
        description: 'Update a rendition definition. Only the given fields are changed. An invalid build configuration is refused with the reason.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updateDefinition(
        #[Schema(description: 'Rendition definition ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: 'ID of the rendition policy controlling who can see it')]
        ?string $policyId = null,
        #[Schema(description: self::BUILD_MODE, enum: [0, 1, 2])]
        ?int $buildMode = null,
        #[Schema(description: self::DEFINITION)]
        ?string $definition = null,
        #[Schema(description: 'ID of the rendition definition whose file is the source of this one, "" to build from the asset source file')]
        ?string $parentId = null,
        #[Schema(definition: ToolSchemas::TARGET)]
        ?int $target = null,
        #[Schema(description: 'Higher first in the lists')]
        ?int $priority = null,
        ?bool $useAsMain = null,
        ?bool $useAsPreview = null,
        ?bool $useAsThumbnail = null,
        ?bool $useAsAnimatedThumbnail = null,
        #[Schema(description: 'Whether the rendition can be downloaded')]
        ?bool $download = null,
        #[Schema(description: 'Whether a user can replace the rendition file')]
        ?bool $substitutable = null,
        #[Schema(description: 'Whether the attribute values are embedded into the file on export')]
        ?bool $writeMetadata = null,
        #[Schema(type: 'object', description: 'Metadata written into the file on export, by tag, e.g. {"IPTC:CopyrightNotice": "ACME"}', additionalProperties: ['type' => 'string'])]
        ?array $metadata = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
        #[Schema(definition: ToolSchemas::TRANSLATIONS)]
        ?array $translations = null,
    ): array {
        $body = ApiClient::withoutNulls([
            'name' => $name,
            'policy' => null !== $policyId ? '/rendition-policies/'.$policyId : null,
            'buildMode' => $buildMode,
            'definition' => $definition,
            'target' => $target,
            'priority' => $priority,
            'useAsMain' => $useAsMain,
            'useAsPreview' => $useAsPreview,
            'useAsThumbnail' => $useAsThumbnail,
            'useAsAnimatedThumbnail' => $useAsAnimatedThumbnail,
            'download' => $download,
            'substitutable' => $substitutable,
            'writeMetadata' => $writeMetadata,
            'metadata' => $metadata,
            'labels' => $labels,
            'translations' => $translations,
        ]);
        if (null !== $parentId) {
            $body['parent'] = '' !== $parentId ? '/rendition-definitions/'.$parentId : null;
        }

        return $this->client->patch('/rendition-definitions/'.rawurlencode($id), $body);
    }

    #[McpTool(
        name: 'duplicate_rendition_definition',
        description: 'Copy a rendition definition in its workspace.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function duplicateDefinition(
        #[Schema(description: 'Rendition definition ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/rendition-definitions/'.rawurlencode($id).'/duplicate');
    }

    /**
     * @return array{sorted: list<string>}
     */
    #[McpTool(
        name: 'sort_rendition_definitions',
        description: 'Reorder the rendition definitions of a workspace: their priority follows the given order, the first one being the highest.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function sortDefinitions(
        #[Schema(description: 'Rendition definition IDs in their new order', items: ['type' => 'string'], minItems: 1)]
        array $ids,
    ): array {
        $ids = array_values($ids);
        $this->client->post('/rendition-definitions/sort', $ids);

        return ['sorted' => $ids];
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_rendition_definition',
        description: 'Delete a rendition definition. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function deleteDefinition(
        #[Schema(description: 'Rendition definition ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/rendition-definitions/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_rendition_policies',
        description: 'List the rendition policies of a workspace. A policy groups rendition definitions and controls who can see their renditions: "public" ones are visible to anyone who can read the asset.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listPolicies(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/rendition-policies', [
            'workspaceId' => $workspaceId,
            'page' => $page,
        ]);
    }

    #[McpTool(
        name: 'create_rendition_policy',
        description: 'Create a rendition policy in a workspace.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function createPolicy(
        #[Schema(description: 'Workspace ID (UUID)')]
        string $workspaceId,
        string $name,
        #[Schema(description: 'Whether the renditions are visible to anyone who can read the asset (otherwise to the users granted on the policy)')]
        bool $public,
        #[Schema(description: 'Whether the renditions can be replaced by anyone who can edit the asset (otherwise by the users granted on the policy)')]
        bool $editable,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
    ): array {
        return $this->client->post('/rendition-policies', ApiClient::withoutNulls([
            'workspace' => '/workspaces/'.$workspaceId,
            'name' => $name,
            'public' => $public,
            'editable' => $editable,
            'labels' => $labels,
        ]));
    }

    #[McpTool(
        name: 'update_rendition_policy',
        description: 'Update a rendition policy. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updatePolicy(
        #[Schema(description: 'Rendition policy ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: 'Whether the renditions are visible to anyone who can read the asset')]
        ?bool $public = null,
        #[Schema(description: 'Whether the renditions can be replaced by anyone who can edit the asset')]
        ?bool $editable = null,
        #[Schema(definition: ToolSchemas::LABELS)]
        ?array $labels = null,
    ): array {
        return $this->client->patch('/rendition-policies/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'public' => $public,
            'editable' => $editable,
            'labels' => $labels,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_rendition_policy',
        description: 'Delete a rendition policy, with its rendition definitions. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function deletePolicy(
        #[Schema(description: 'Rendition policy ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/rendition-policies/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_asset_renditions',
        description: 'List the renditions of an asset (one per rendition definition the user can see), with their file URL and build status.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listAssetRenditions(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->getCollection('/renditions', ['assetId' => $assetId]);
    }
}
