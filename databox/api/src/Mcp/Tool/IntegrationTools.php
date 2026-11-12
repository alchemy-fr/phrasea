<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

final readonly class IntegrationTools
{
    private const string CONFIG_YAML = 'Configuration as YAML, following the reference of the integration type (get_integration_type). Replaces the current configuration.';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_integration_types',
        description: 'List the available integration types (AI taggers, transcription, workflows, external services…), with their features.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listTypes(): array
    {
        $result = $this->client->getCollection('/integration-types');
        // The configuration references are long: get_integration_type returns them for one type.
        $result['items'] = array_map(static function (array $type): array {
            unset($type['reference'], $type['references'], $type['schema']);

            return $type;
        }, $result['items']);

        return $result;
    }

    #[McpTool(
        name: 'get_integration_type',
        description: 'Get an integration type with the reference of its configuration. Read it before writing the configuration of an integration.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getType(
        #[Schema(description: 'Integration type ID, from list_integration_types')]
        string $id,
    ): array {
        return $this->client->get('/integration-types/'.rawurlencode($id));
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_integrations',
        description: 'List the integrations set up in the workspaces visible to the current user, or on the whole instance ("global").',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the integrations of this workspace')]
        ?string $workspaceId = null,
        #[Schema(description: 'Only the integrations of no workspace (ignored with "workspaceId")')]
        ?bool $global = null,
        #[Schema(description: 'Only the enabled (true) or disabled (false) integrations')]
        ?bool $enabled = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/integrations', ApiClient::withoutNulls([
            'workspace' => $workspaceId,
            'global' => null !== $global ? ($global ? 'true' : 'false') : null,
            'enabled' => null !== $enabled ? ($enabled ? 'true' : 'false') : null,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'get_integration',
        description: 'Get an integration with its configuration.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Integration ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/integrations/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_integration',
        description: 'Set up an integration in a workspace, or on the whole instance when the type allows it. Unset configuration options get their defaults.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(description: 'Integration type ID, from list_integration_types')]
        string $integration,
        #[Schema(description: 'Workspace ID (UUID); unset for an instance-wide integration')]
        ?string $workspaceId = null,
        #[Schema(description: 'Label of the integration')]
        ?string $name = null,
        #[Schema(description: self::CONFIG_YAML)]
        ?string $configYaml = null,
        ?bool $enabled = null,
        #[Schema(description: 'Whether its features are offered to every user (otherwise to the users granted on it)')]
        ?bool $public = null,
        #[Schema(description: 'Workflow integrations only: IDs of the integrations that must run before this one', items: ['type' => 'string'])]
        ?array $needs = null,
        #[Schema(description: 'Workflow integrations only: condition for the integration to run')]
        ?string $if = null,
    ): array {
        return $this->client->post('/integrations', ApiClient::withoutNulls([
            'integration' => $integration,
            'workspace' => null !== $workspaceId ? '/workspaces/'.$workspaceId : null,
            'name' => $name,
            'configYaml' => $configYaml,
            'enabled' => $enabled,
            'public' => $public,
            'needs' => null !== $needs ? array_map(static fn (string $id): string => '/integrations/'.$id, array_values($needs)) : null,
            'if' => $if,
        ]));
    }

    #[McpTool(
        name: 'update_integration',
        description: 'Update an integration. Only the given fields are changed.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Integration ID (UUID)')]
        string $id,
        #[Schema(description: 'Label of the integration')]
        ?string $name = null,
        #[Schema(description: self::CONFIG_YAML)]
        ?string $configYaml = null,
        ?bool $enabled = null,
        #[Schema(description: 'Whether its features are offered to every user (otherwise to the users granted on it)')]
        ?bool $public = null,
        #[Schema(description: 'Workflow integrations only: IDs of the integrations that must run before this one', items: ['type' => 'string'])]
        ?array $needs = null,
        #[Schema(description: 'Workflow integrations only: condition for the integration to run, "" to remove it')]
        ?string $if = null,
    ): array {
        $path = '/integrations/'.rawurlencode($id);
        // The input requires the integration type, which an update cannot change.
        $current = $this->client->get($path);

        return $this->client->patch($path, ApiClient::withoutNulls([
            'integration' => $current['integration'] ?? null,
            'name' => $name,
            'configYaml' => $configYaml,
            'enabled' => $enabled,
            'public' => $public,
            'needs' => null !== $needs ? array_map(static fn (string $id): string => '/integrations/'.$id, array_values($needs)) : null,
            'if' => $if,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_integration',
        description: 'Delete an integration with its data. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Integration ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/integrations/'.rawurlencode($id));

        return ['deleted' => $id];
    }
}
