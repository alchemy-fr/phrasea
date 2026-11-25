<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The workflows are the background processing of the assets: the ingest of a new asset (metadata reading,
 * renditions, integrations…) runs as a workflow of jobs.
 */
final readonly class WorkflowTools
{
    private const string STATUS = 'Status: 0 = running, 1 = succeeded, 2 = failed, 3 = cancelled';

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_workflows',
        description: 'List the workflow runs (background processing) of assets, the last started first, with their status. A user who is not an administrator only gets the runs of the assets they can edit, and must give their IDs.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Only the runs of these assets', items: ['type' => 'string'])]
        ?array $assetIds = null,
        #[Schema(description: self::STATUS, enum: [0, 1, 2, 3])]
        ?int $status = null,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/workflows', ApiClient::withoutNulls([
            'asset' => null !== $assetIds ? array_values($assetIds) : null,
            'status' => $status,
            'page' => $page,
        ]));
    }

    #[McpTool(
        name: 'get_workflow',
        description: 'Get a workflow run with its jobs, their status, duration, inputs, outputs and errors: the job IDs are those to give to rerun_workflow_job.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Workflow run ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/workflows/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'rerun_workflow_job',
        description: 'Run a job of a workflow run again (e.g. a failed one); the jobs needing it then run as usual. Returns the workflow run.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function rerunJob(
        #[Schema(description: 'Workflow run ID (UUID)')]
        string $id,
        #[Schema(description: 'Job ID, as listed by get_workflow')]
        string $jobId,
    ): array {
        return $this->client->post(sprintf('/workflows/%s/jobs/%s/rerun', rawurlencode($id), rawurlencode($jobId)));
    }

    #[McpTool(
        name: 'cancel_workflow',
        description: 'Cancel a running workflow run: its pending jobs are not run. Returns the workflow run.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function cancel(
        #[Schema(description: 'Workflow run ID (UUID)')]
        string $id,
    ): array {
        return $this->client->post('/workflows/'.rawurlencode($id).'/cancel');
    }

    #[McpTool(
        name: 'reprocess_asset',
        description: 'Run the ingest workflow of an asset again, in the background: metadata reading, renditions, integrations… Refused while a workflow is still running on the asset (cancel it with cancel_workflow first). Follow the new run with list_workflows.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function reprocessAsset(
        #[Schema(description: 'Asset ID (UUID)')]
        string $assetId,
    ): array {
        return $this->client->put('/assets/'.rawurlencode($assetId).'/trigger-workflow');
    }
}
