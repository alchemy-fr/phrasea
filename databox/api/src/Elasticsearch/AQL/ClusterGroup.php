<?php

namespace App\Elasticsearch\AQL;

final class ClusterGroup
{
    public function __construct(
        private mixed $item,
        private readonly bool $isConstant = false,
        private readonly ?string $workspaceId = null,
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

    public function getWorkspaceId(): ?string
    {
        return $this->workspaceId;
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
                    || null === $l->getWorkspaceId()
                    || null === $r->getWorkspaceId()
                    || $l->getWorkspaceId() === $r->getWorkspaceId()
                ) {
                    $ref = $l->isConstant ? $r : ($l->getWorkspaceId() ? $l : $r);
                    $groups[] = $ref->convert($merge($l->getItem(), $r->getItem()));
                }
            }
        }

        return $groups;
    }
}
