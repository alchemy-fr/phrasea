<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

final class ClusterGroup
{
    /**
     * @param list<array{w: string, p: string[]}> $hiddenScopes the assets on which asset policies hide the field (see HiddenAttributeScope)
     */
    public function __construct(
        private mixed $item,
        private readonly bool $isConstant = false,
        private readonly array $workspaceIds = [],
        private readonly ?array $locales = null,
        private array $hiddenScopes = [],
    ) {
    }

    public function convert(mixed $item): self
    {
        $new = clone $this;
        $new->item = $item;

        return $new;
    }

    public function getItem(): mixed
    {
        return $this->item;
    }

    public function getWorkspaceIds(): array
    {
        return $this->workspaceIds;
    }

    /**
     * @return list<array{w: string, p: string[]}>
     */
    public function getHiddenScopes(): array
    {
        return $this->hiddenScopes;
    }

    public function hasSameWorkspaceIds(self $other): bool
    {
        return empty(array_diff($this->workspaceIds, $other->workspaceIds))
            && empty(array_diff($other->workspaceIds, $this->workspaceIds));
    }

    public function getLocales(): ?array
    {
        return $this->locales;
    }

    /**
     * @param self[] $left
     * @param self[] $right
     *
     * @return self[]
     */
    public static function mix(array $left, array $right, \Closure $merge): array
    {
        $groups = [];

        foreach ($left as $l) {
            foreach ($right as $r) {
                if (
                    $l->isConstant
                    || $r->isConstant
                    || empty($l->getWorkspaceIds())
                    || empty($r->getWorkspaceIds())
                    || $l->hasSameWorkspaceIds($r)
                ) {
                    $ref = $l->isConstant ? $r : (!empty($l->getWorkspaceIds()) ? $l : $r);
                    $group = $ref->convert($merge($l->getItem(), $r->getItem()));
                    // An expression reading two fields must ignore the assets where either is hidden
                    $group->hiddenScopes = array_values(array_unique([...$l->hiddenScopes, ...$r->hiddenScopes], SORT_REGULAR));
                    $groups[] = $group;
                }
            }
        }

        return $groups;
    }
}
