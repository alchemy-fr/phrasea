<?php

namespace App\Elasticsearch\AQL;

final class ClusterGroup
{
    public function __construct(
        private mixed $item,
        private readonly bool $isConstant = false,
        private readonly array $workspaceIds = [],
        private readonly ?array $locales = null,
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
                    $groups[] = $ref->convert($merge($l->getItem(), $r->getItem()));
                }
            }
        }

        return $groups;
    }
}
