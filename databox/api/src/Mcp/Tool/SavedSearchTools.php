<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * A saved search stores the criteria of an asset search (full-text query, AQL conditions, sort) under a name;
 * its automations apply actions to its results, on demand or on a schedule.
 */
final readonly class SavedSearchTools
{
    private const string PRIVACY = 'Visibility: 0 = secret (owner and granted users only), 1 = private (reachable by its link, not listed), 2 = public (listed to every user); publishing needs the right to';

    private const array ORDER = [
        'type' => ['object', 'null'],
        'description' => 'Sort of the results, by attribute slug or built-in field, e.g. {"@createdAt": "desc"}; by relevance when unset',
        'additionalProperties' => ['type' => 'string', 'enum' => ['asc', 'desc']],
    ];

    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_saved_searches',
        description: 'List the saved searches the current user can see (their own, the shared and the public ones), with their criteria ("data": query, conditions, sortBy). Run one with search_assets and its savedSearchId.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function list(
        #[Schema(description: 'Search on the name')]
        ?string $query = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $limit = 30,
    ): array {
        return $this->client->getCollection('/saved-searches', ApiClient::withoutNulls([
            'query' => $query,
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    #[McpTool(
        name: 'get_saved_search',
        description: 'Get a saved search with its criteria ("data": query, conditions, sortBy) and capabilities.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function get(
        #[Schema(description: 'Saved search ID (UUID)')]
        string $id,
    ): array {
        return $this->client->get('/saved-searches/'.rawurlencode($id));
    }

    #[McpTool(
        name: 'create_saved_search',
        description: 'Save the criteria of an asset search under a name, as search_assets takes them. Check them with search_assets first.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function create(
        string $name,
        #[Schema(description: 'Full-text query')]
        ?string $query = null,
        #[Schema(description: 'AQL conditions, combined with AND (see search_assets)', items: ['type' => 'string'])]
        ?array $conditions = null,
        #[Schema(definition: self::ORDER)]
        ?array $order = null,
        #[Schema(description: self::PRIVACY, enum: [0, 1, 2])]
        ?int $privacy = null,
    ): array {
        return $this->client->post('/saved-searches', ApiClient::withoutNulls([
            'name' => $name,
            'privacy' => $privacy,
            'data' => self::data($query, $conditions, $order),
        ]));
    }

    #[McpTool(
        name: 'update_saved_search',
        description: 'Rename a saved search, change its visibility or its criteria. Only the given fields are changed, except the criteria: giving any of query, conditions or order replaces the whole criteria, so give all of those to keep.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
    )]
    public function update(
        #[Schema(description: 'Saved search ID (UUID)')]
        string $id,
        ?string $name = null,
        #[Schema(description: self::PRIVACY, enum: [0, 1, 2])]
        ?int $privacy = null,
        #[Schema(description: 'Full-text query')]
        ?string $query = null,
        #[Schema(description: 'AQL conditions, combined with AND (see search_assets)', items: ['type' => 'string'])]
        ?array $conditions = null,
        #[Schema(definition: self::ORDER)]
        ?array $order = null,
    ): array {
        return $this->client->patch('/saved-searches/'.rawurlencode($id), ApiClient::withoutNulls([
            'name' => $name,
            'privacy' => $privacy,
            'data' => null !== $query || null !== $conditions || null !== $order ? self::data($query, $conditions, $order) : null,
        ]));
    }

    /**
     * @return array{deleted: string}
     */
    #[McpTool(
        name: 'delete_saved_search',
        description: 'Delete a saved search, with its automations. The assets are kept. This cannot be undone.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true),
    )]
    public function delete(
        #[Schema(description: 'Saved search ID (UUID)')]
        string $id,
    ): array {
        $this->client->delete('/saved-searches/'.rawurlencode($id));

        return ['deleted' => $id];
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_saved_search_automations',
        description: 'List the automations of a saved search: the actions they apply to its results, their schedule (cron, none for on demand only), their scope (every result or only the new ones) and their last run.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listAutomations(
        #[Schema(description: 'Saved search ID (UUID)')]
        string $savedSearchId,
    ): array {
        return $this->client->getCollection('/saved-search-automations', ['savedSearch' => $savedSearchId]);
    }

    #[McpTool(
        name: 'run_saved_search_automation',
        description: 'Run an automation now (even a disabled one), as the owner of its saved search. The run may go on in the background: the result is the run, with its status ("pending", "running", "success", "partial", "failed"…) and its counts; follow it with list_automation_runs. Its actions (listed by list_saved_search_automations) may move, trash, publish or edit the assets: a dry run only counts the matching and new results, without running any action.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false),
    )]
    public function runAutomation(
        #[Schema(description: 'Automation ID (UUID)')]
        string $id,
        #[Schema(description: 'Only count the results, without running any action')]
        bool $dryRun = false,
    ): array {
        return $this->client->post('/saved-search-automations/'.rawurlencode($id).'/run', [
            'dryRun' => $dryRun,
        ]);
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_automation_runs',
        description: 'List the runs of an automation, the last one first: status, counts (matched, new, processed, failed), result and errors of each action.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listRuns(
        #[Schema(description: 'Automation ID (UUID)')]
        string $automationId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/automation-runs', [
            'automation' => $automationId,
            'page' => $page,
        ]);
    }

    /**
     * The criteria in the format of the client: conditions as {id, query}, sort as [{a: field, w: 0 (asc) | 1 (desc)}].
     */
    private static function data(?string $query, ?array $conditions, ?array $order): array
    {
        $sortBy = [];
        foreach ($order ?? [] as $field => $direction) {
            $sortBy[] = ['a' => (string) $field, 'w' => 'desc' === $direction ? 1 : 0, 'g' => false];
        }

        return [
            'query' => $query ?? '',
            'conditions' => array_map(
                static fn (int $i, string $condition): array => ['id' => 'c'.($i + 1), 'query' => $condition],
                array_keys(array_values($conditions ?? [])),
                array_values($conditions ?? []),
            ),
            'sortBy' => $sortBy,
        ];
    }
}
