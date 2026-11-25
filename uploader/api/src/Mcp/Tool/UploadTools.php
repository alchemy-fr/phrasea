<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The model never sends file bytes: the server downloads the file from a URL and commits it.
 */
final readonly class UploadTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{result?: true, errors?: array<string, list<string>>}
     */
    #[McpTool(
        name: 'upload_from_url',
        description: 'Upload a file to a target from a public URL: the server downloads it in the background, then commits it with the form data, as one commit of one file. Returns {"result": true} once the download is queued, or {"errors": …} by field key when the form data is invalid (nothing is queued then); read the form with get_target_form_schema and check the data with validate_form_data first. The download happens later: follow it with list_commits. URLs of private networks (localhost, LAN…) are refused when the download runs, as are the file types the uploader does not accept. Downloads are rate limited per user (30 per hour by default): over it, the call fails with a 429 telling when to retry.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function uploadFromUrl(
        #[Schema(description: 'Public http(s) URL of the file to download', format: 'uri')]
        string $url,
        #[Schema(description: 'Target ID (UUID)')]
        string $targetId,
        #[Schema(type: 'object', description: 'Form data: the values keyed by the field keys of the form of the target', additionalProperties: true)]
        ?array $formData = null,
        #[Schema(definition: [
            'type' => 'object',
            'description' => 'Data read by Databox to replace a rendition of an existing asset with the file, instead of creating an asset',
            'properties' => [
                'targetAsset' => ['type' => 'string', 'description' => 'ID of the Databox asset'],
                'targetRendition' => ['type' => 'string', 'description' => 'Name of the rendition definition to replace'],
            ],
            'additionalProperties' => false,
        ])]
        ?array $data = null,
    ): array {
        return $this->client->post('/downloads', ApiClient::withoutNulls([
            'url' => $url,
            'target' => '/targets/'.rawurlencode($targetId),
            'formData' => $formData,
            'data' => $data,
        ]));
    }
}
