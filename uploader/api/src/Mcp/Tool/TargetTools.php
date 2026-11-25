<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Targets receive the uploaded files; their form defines the data sent along with them.
 */
final readonly class TargetTools
{
    /**
     * The settings of a target that update_target can remove.
     */
    private const array CLEARABLE_FIELDS = ['slug', 'description', 'targetUrl', 'defaultDestination', 'authorizationScheme', 'authorizationKey', 'allowedGroups'];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null}
     */
    #[McpTool(
        name: 'list_targets',
        description: 'List every upload target the current user can upload to, in one call.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Case-insensitive search on the name')]
        ?string $query = null,
    ): array {
        $result = $this->client->getCollection('/targets', ApiClient::withoutNulls([
            'query' => $query,
        ]));

        return [
            'items' => $result['items'],
            'totalItems' => $result['totalItems'],
        ];
    }

    #[McpTool(
        name: 'get_target',
        description: 'Get an upload target.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/targets/'.rawurlencode($id));
    }

    /**
     * @return array{targetId: string, form: array|null}
     */
    #[McpTool(
        name: 'get_target_form_schema',
        description: 'Get the upload form of a target: its fields ("fields": key, type, label, required, choices…) and the Databox workspace they fill. "form" is null when the target has no form. Form data is an object keyed by the field keys.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getFormSchema(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
    ): array {
        $form = $this->client->get('/targets/'.rawurlencode($id).'/form');

        return [
            'targetId' => $id,
            'form' => is_array($form) && [] !== $form ? $form : null,
        ];
    }

    /**
     * @return array{items: list<array{value: string, label: string, color: string|null, emoji: string|null}>, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_form_field_options',
        description: 'List the values a user may pick for an entity field (type "entity", e.g. tags, read from Databox) of the upload form of a target, 30 per page. The form data holds the "value" of the chosen options (entity IDs), a list for a multiple field: call it before validate_form_data or upload_from_url to fill these fields.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listFormFieldOptions(
        #[Schema(description: 'Target ID (UUID)')]
        string $targetId,
        #[Schema(description: 'Key of the field in the form')]
        string $fieldKey,
        #[Schema(description: 'Search on the labels')]
        ?string $query = null,
        #[Schema(description: 'Only these values (entity IDs), e.g. to read the labels of values already set', items: ['type' => 'string'])]
        ?array $ids = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        $response = $this->client->get(
            '/targets/'.rawurlencode($targetId).'/form-fields/'.rawurlencode($fieldKey).'/options',
            ApiClient::withoutNulls([
                'query' => $query,
                'ids' => null !== $ids ? array_values($ids) : null,
                'page' => $page,
            ]),
        );

        return [
            'items' => array_values($response['member'] ?? []),
            'page' => $page,
            'hasNextPage' => (bool) ($response['hasMore'] ?? false),
        ];
    }

    /**
     * @return array{valid: bool, errors: array<string, list<string>>}
     */
    #[McpTool(
        name: 'validate_form_data',
        description: 'Validate form data against the upload form of a target, without uploading anything. Returns the errors by field key ("_form" for the whole form); "valid" is true when there is none. Read the form with get_target_form_schema first, and the values of its entity fields with list_form_field_options.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function validateFormData(
        #[Schema(description: 'Target ID (UUID)')]
        string $targetId,
        #[Schema(type: 'object', description: 'Form data: the values keyed by the field keys of the form', additionalProperties: true)]
        array $data,
    ): array {
        $response = $this->client->post('/form/validate', [
            'target' => '/targets/'.rawurlencode($targetId),
            'data' => $data,
        ]);
        $errors = $response['errors'] ?? [];

        return [
            'valid' => [] === $errors,
            'errors' => $errors,
        ];
    }

    #[McpTool(
        name: 'get_target_settings',
        description: 'Get a target with its delivery settings: target URL, default destination, authorization scheme, whether it has an authorization key (never the key itself) and the Keycloak groups allowed to upload. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getSettings(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/targets/'.rawurlencode($id).'/settings');
    }

    #[McpTool(
        name: 'create_target',
        description: 'Create an upload target. Each commit is notified to "targetUrl" (https, e.g. the incoming-commit URL of a Databox Uploader integration) with "authorizationScheme" and "authorizationKey"; without URL, the destination lists the commits itself (pull mode). Set its form with update_target_form afterwards. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        #[Schema(maxLength: 1000)]
        string $name,
        #[Schema(description: 'Unique identifier: lower case letters, digits, "-" and "_", starting with a letter', maxLength: 100)]
        ?string $slug = null,
        ?string $description = null,
        #[Schema(description: 'URL notified of each commit', maxLength: 255)]
        ?string $targetUrl = null,
        #[Schema(description: 'Destination of the files in the target (e.g. a Databox collection)', maxLength: 255)]
        ?string $defaultDestination = null,
        #[Schema(description: 'Scheme of the Authorization header sent to the target URL (e.g. "ApiKey", "OAuth"; "avoid" sends none)', maxLength: 100)]
        ?string $authorizationScheme = null,
        #[Schema(description: 'Secret sent to the target URL; it is never read back', maxLength: 2000)]
        ?string $authorizationKey = null,
        #[Schema(description: 'IDs of the Keycloak groups allowed to upload; empty or unset: everybody', items: ['type' => 'string'])]
        ?array $allowedGroups = null,
    ): array {
        return $this->client->post('/targets', self::buildBody(
            name: $name,
            slug: $slug,
            description: $description,
            targetUrl: $targetUrl,
            defaultDestination: $defaultDestination,
            authorizationScheme: $authorizationScheme,
            authorizationKey: $authorizationKey,
            allowedGroups: $allowedGroups,
        ));
    }

    #[McpTool(
        name: 'update_target',
        description: 'Update an upload target: only the given fields change, and the ones listed in "clear" are removed. Changing the origin of the target URL removes the authorization key, unless a key is given along with it. Returns the target with its settings. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
        #[Schema(maxLength: 1000)]
        ?string $name = null,
        #[Schema(maxLength: 100)]
        ?string $slug = null,
        ?string $description = null,
        #[Schema(maxLength: 255)]
        ?string $targetUrl = null,
        #[Schema(maxLength: 255)]
        ?string $defaultDestination = null,
        #[Schema(maxLength: 100)]
        ?string $authorizationScheme = null,
        #[Schema(maxLength: 2000)]
        ?string $authorizationKey = null,
        #[Schema(description: 'IDs of the Keycloak groups allowed to upload; empty: everybody', items: ['type' => 'string'])]
        ?array $allowedGroups = null,
        #[Schema(description: 'Settings to remove', items: ['type' => 'string', 'enum' => self::CLEARABLE_FIELDS])]
        ?array $clear = null,
    ): array {
        $body = self::buildBody(
            name: $name,
            slug: $slug,
            description: $description,
            targetUrl: $targetUrl,
            defaultDestination: $defaultDestination,
            authorizationScheme: $authorizationScheme,
            authorizationKey: $authorizationKey,
            allowedGroups: $allowedGroups,
        );
        foreach (array_intersect(self::CLEARABLE_FIELDS, $clear ?? []) as $field) {
            $body[$field] = null;
        }

        return $this->client->patch('/targets/'.rawurlencode($id), $body);
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_target',
        description: 'Delete an upload target, with its upload links. This cannot be undone. Requires the admin role.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/targets/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    /**
     * @return array{targetId: string, form: array|null}
     */
    #[McpTool(
        name: 'update_target_form',
        description: 'Replace the upload form of a target with a form definition, in the format returned by get_target_form_schema ("version", "workspaceId", "fields": id, source, key, type, label, required…); null removes the form. Returns the stored form. Requires the admin role or the permission to edit the forms.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updateForm(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
        #[Schema(definition: ['type' => ['object', 'null'], 'description' => 'The form definition, null to remove the form', 'additionalProperties' => true])]
        ?array $form,
    ): array {
        $result = $this->client->put('/targets/'.rawurlencode($id).'/form', [
            'form' => $form,
        ]);

        return [
            'targetId' => $id,
            'form' => [] !== $result ? $result : null,
        ];
    }

    /**
     * @return array{targetId: string, data: array|null}
     */
    #[McpTool(
        name: 'get_target_data',
        description: 'Get the data of a target: values merged into the form data of every commit sent to it, over what the user entered. "data" is null when the target has none. Requires the admin role or the permission to edit the target data.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getData(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
    ): array {
        $data = $this->client->get('/targets/'.rawurlencode($id).'/data');

        return [
            'targetId' => $id,
            'data' => is_array($data) && [] !== $data ? $data : null,
        ];
    }

    /**
     * @return array{targetId: string, data: array|null}
     */
    #[McpTool(
        name: 'update_target_data',
        description: 'Replace the data of a target: values keyed by field key, merged into the form data of every commit sent to it; null removes them. Returns the stored data. Requires the admin role or the permission to edit the target data.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function updateData(
        #[Schema(description: 'Target ID (UUID)')]
        string $id,
        #[Schema(definition: ['type' => ['object', 'null'], 'description' => 'The values keyed by field key, null to remove them', 'additionalProperties' => true])]
        ?array $data,
    ): array {
        $result = $this->client->put('/targets/'.rawurlencode($id).'/data', [
            'data' => $data,
        ]);

        return [
            'targetId' => $id,
            'data' => [] !== $result ? $result : null,
        ];
    }

    private static function buildBody(
        ?string $name,
        ?string $slug,
        ?string $description,
        ?string $targetUrl,
        ?string $defaultDestination,
        ?string $authorizationScheme,
        ?string $authorizationKey,
        ?array $allowedGroups,
    ): array {
        return ApiClient::withoutNulls([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'targetUrl' => $targetUrl,
            'defaultDestination' => $defaultDestination,
            'authorizationScheme' => $authorizationScheme,
            'authorizationKey' => $authorizationKey,
            'allowedGroups' => null !== $allowedGroups ? array_values($allowedGroups) : null,
        ]);
    }
}
