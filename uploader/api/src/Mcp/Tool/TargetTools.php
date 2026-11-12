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
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, page: int}
     */
    #[McpTool(
        name: 'list_targets',
        description: 'List the upload targets the current user can upload to. Targets come by pages of 30 from which those the user cannot use are removed: ask for the next page until one comes back empty.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Case-insensitive search on the name')]
        ?string $query = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        $result = $this->client->getCollection('/targets', ApiClient::withoutNulls([
            'query' => $query,
            'page' => $page,
        ]));

        // Computed by the API on the filtered page only: they would mislead.
        return [
            'items' => $result['items'],
            'page' => $result['page'],
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
     * @return array{valid: bool, errors: array<string, list<string>>}
     */
    #[McpTool(
        name: 'validate_form_data',
        description: 'Validate form data against the upload form of a target, without uploading anything. Returns the errors by field key ("_form" for the whole form); "valid" is true when there is none. Read the form with get_target_form_schema first.',
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
}
