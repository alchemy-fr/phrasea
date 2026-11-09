<?php

declare(strict_types=1);

namespace App\Databox;

use App\Form\Builder\FieldType;

/**
 * What the form builder and the forms read from Databox, shaped for the Uploader client.
 */
final readonly class DataboxCatalog
{
    public const int PAGE_SIZE = 30;
    private const int MAX_PAGES = 20;
    private const int TARGET_ASSET = 1;

    public function __construct(private DataboxClientInterface $client)
    {
    }

    /**
     * @return list<array{id: string, name: string, enabledLocales: string[]}>
     */
    public function getWorkspaces(): array
    {
        return array_map(self::mapWorkspace(...), $this->getAll('/workspaces'));
    }

    /**
     * @return array{id: string, name: string, enabledLocales: string[]}
     */
    public function getWorkspace(string $id): array
    {
        return self::mapWorkspace($this->client->get('/workspaces/'.rawurlencode($id)));
    }

    /**
     * @return array{id: string, title: string, workspaceId: ?string}
     */
    public function getCollection(string $id): array
    {
        $collection = $this->client->get('/collections/'.rawurlencode($id));
        $workspace = $collection['workspace'] ?? null;

        return [
            'id' => (string) $collection['id'],
            'title' => (string) ($collection['absoluteDisplayName'] ?? $collection['displayName'] ?? $collection['title'] ?? $collection['id']),
            'workspaceId' => match (true) {
                is_array($workspace) => $workspace['id'] ?? null,
                is_string($workspace) => basename($workspace),
                default => null,
            },
        ];
    }

    /**
     * The attributes of the workspace an uploader can fill.
     *
     * @return list<array<string, mixed>>
     */
    public function getFields(string $workspaceId): array
    {
        $fields = [];
        foreach ($this->getAll('/attribute-definitions', [
            'workspaceId' => $workspaceId,
            'enabled' => 'true',
            'target' => self::TARGET_ASSET,
        ]) as $definition) {
            $type = FieldType::fromDataboxType((string) ($definition['type'] ?? ''));
            if (null === $type || false === ($definition['editable'] ?? true)) {
                continue;
            }
            $entityList = $definition['entityList'] ?? null;
            if (FieldType::Entity === $type && !is_array($entityList)) {
                continue;
            }

            $label = $definition['translations']['name'] ?? [];
            $fields[] = [
                'id' => (string) $definition['id'],
                'slug' => (string) $definition['slug'],
                'name' => (string) $definition['name'],
                'displayName' => (string) ($definition['displayName'] ?? $definition['name']),
                'label' => is_array($label) ? $label : [],
                'databoxType' => (string) $definition['type'],
                'type' => $type->value,
                'multiple' => (bool) ($definition['multiple'] ?? false),
                'translatable' => (bool) ($definition['translatable'] ?? false),
                'required' => (bool) ($definition['required'] ?? false),
                'minLength' => $definition['minLength'] ?? null,
                'maxLength' => $definition['maxLength'] ?? null,
                'entityList' => is_array($entityList) ? [
                    'id' => (string) $entityList['id'],
                    'name' => (string) ($entityList['name'] ?? ''),
                ] : null,
            ];
        }

        return $fields;
    }

    /**
     * @param string[] $ids
     *
     * @return array{member: list<array{value: string, label: string, color: ?string, emoji: ?string}>, hasMore: bool}
     */
    public function getEntityOptions(string $listId, ?string $query, array $ids, int $page, ?string $locale): array
    {
        $params = ['list' => $listId, 'limit' => self::PAGE_SIZE, 'page' => max(1, $page)];
        if ([] !== $ids) {
            $params['ids'] = array_values($ids);
            $params['limit'] = count($ids);
            $params['page'] = 1;
        } elseif (null !== $query && '' !== trim($query)) {
            $params['query'] = $query;
        } else {
            $params['order[position]'] = 'asc';
            $params['order[value]'] = 'asc';
        }

        $data = $this->client->get('/attribute-entities', $params);

        return [
            'member' => array_map(static fn (array $entity): array => [
                'value' => (string) $entity['id'],
                'label' => (string) (($locale ? ($entity['translations'][$locale] ?? null) : null) ?? $entity['value'] ?? $entity['id']),
                'color' => $entity['color'] ?? null,
                'emoji' => $entity['emoji'] ?? null,
            ], self::members($data)),
            'hasMore' => [] === $ids && self::hasNext($data),
        ];
    }

    /**
     * @param string[] $ids
     *
     * @return array{member: list<array{value: string, label: string, color: ?string, emoji: ?string}>, hasMore: bool}
     */
    public function getTagOptions(string $workspaceId, ?string $query, array $ids, int $page): array
    {
        if ([] !== $ids) {
            $tags = [];
            foreach (array_unique($ids) as $id) {
                try {
                    $tag = $this->client->get('/tags/'.rawurlencode((string) $id));
                } catch (DataboxException $e) {
                    if (404 === $e->statusCode) {
                        continue;
                    }
                    throw $e;
                }
                $tagWorkspaceId = self::relationId($tag['workspace'] ?? null);
                if (null === $tagWorkspaceId || $tagWorkspaceId === $workspaceId) {
                    $tags[] = $tag;
                }
            }

            return ['member' => array_map(self::mapTag(...), $tags), 'hasMore' => false];
        }

        $params = ['workspace' => $workspaceId, 'limit' => self::PAGE_SIZE, 'page' => max(1, $page)];
        if (null !== $query && '' !== trim($query)) {
            $params['query'] = $query;
        }
        $data = $this->client->get('/tags', $params);

        return [
            'member' => array_map(self::mapTag(...), self::members($data)),
            'hasMore' => self::hasNext($data),
        ];
    }

    /**
     * @param string[] $ids
     *
     * @return string[] the ids that are not values of the list
     */
    public function findMissingEntities(string $listId, array $ids): array
    {
        $found = array_column($this->getEntityOptions($listId, null, $ids, 1, null)['member'], 'value');

        return array_values(array_diff($ids, $found));
    }

    /**
     * @param string[] $ids
     *
     * @return string[] the ids that are not tags of the workspace
     */
    public function findMissingTags(string $workspaceId, array $ids): array
    {
        $found = array_column($this->getTagOptions($workspaceId, null, $ids, 1)['member'], 'value');

        return array_values(array_diff($ids, $found));
    }

    private function getAll(string $path, array $query = []): array
    {
        $items = [];
        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $data = $this->client->get($path, $query + ['limit' => 100, 'page' => $page]);
            array_push($items, ...self::members($data));
            if (!self::hasNext($data)) {
                break;
            }
        }

        return $items;
    }

    private static function members(array $data): array
    {
        return array_values(array_filter($data['member'] ?? $data['hydra:member'] ?? [], is_array(...)));
    }

    private static function hasNext(array $data): bool
    {
        return isset($data['view']['next']) || isset($data['hydra:view']['hydra:next']);
    }

    private static function relationId(mixed $relation): ?string
    {
        return match (true) {
            is_array($relation) => isset($relation['id']) ? (string) $relation['id'] : null,
            is_string($relation) => basename($relation),
            default => null,
        };
    }

    private static function mapWorkspace(array $workspace): array
    {
        return [
            'id' => (string) $workspace['id'],
            'name' => (string) ($workspace['displayName'] ?? $workspace['name'] ?? $workspace['id']),
            'enabledLocales' => array_values(array_filter($workspace['enabledLocales'] ?? [], is_string(...))),
        ];
    }

    private static function mapTag(array $tag): array
    {
        return [
            'value' => (string) $tag['id'],
            'label' => (string) ($tag['displayName'] ?? $tag['name'] ?? $tag['id']),
            'color' => $tag['color'] ?? null,
            'emoji' => null,
        ];
    }
}
