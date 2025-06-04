<?php

namespace Alchemy\ESBundle\Indexer;

use Doctrine\Common\Util\ClassUtils;

class DependencyStack
{
    /**
     * @var EntityGroup[]
     */
    private array $parents;

    /**
     * @var EntityGroup[][]
     */
    private array $dependencies = [];

    private int $dependencyCount = 0;

    public function __construct(
        private \Closure $relay,
        private readonly int $batchSize,
        private readonly int $depth,
        private readonly array $currentBatch,
        array $previousParents,
    ) {
        $this->parents = $previousParents;
    }

    public function getParents(): array
    {
        return $this->parents;
    }

    /**
     * @return EntityGroup[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getObjects(): array
    {
        return array_map(fn (array $action): array => array_map(function (EntityGroup $group): array {
            return array_keys($group->getIds());
        }, $action), $this->dependencies);
    }

    public function addDependency(string $class, string $id, Operation $operation = Operation::Upsert): self
    {
        $class = ClassUtils::getRealClass($class);
        if (
            (isset($this->parents[$class]) && $this->parents[$class]->has($id))
            || $this->isInBatch($class, $id)
        ) {
            return $this;
        }

        $this->dependencies[$class][$operation->value] ??= new EntityGroup();
        $this->dependencies[$class][$operation->value]->add($id);

        if (++$this->dependencyCount > $this->batchSize) {
            return $this->release();
        }

        return $this;
    }

    private function release(): self
    {
        $relay = new self($this->relay, $this->batchSize, $this->depth, $this->currentBatch, $this->parents);

        $relayFn = $this->relay;
        $relayFn($this, $relay);

        return $relay;
    }

    private function isInBatch(string $class, string $id): bool
    {
        if (!isset($this->currentBatch[$class])) {
            return false;
        }

        $b = $this->currentBatch[$class];

        return in_array($id, $b[Operation::Upsert->value] ?? $b[Operation::Insert->value] ?? [], true);
    }

    public function addParent(string $class, string $id): void
    {
        $class = ClassUtils::getRealClass($class);
        $this->parents[$class] ??= new EntityGroup();
        $this->parents[$class]->add($id);
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getDependencyCount(): int
    {
        return $this->dependencyCount;
    }
}
