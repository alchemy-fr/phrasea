<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractEntityFacet extends AbstractFacet
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $entity = $this->loadEntity($bucket['key']);
        if (null === $entity) {
            return null;
        }

        $newKey = [
            'value' => $bucket['key'],
            'label' => $this->resolveLabel($entity),
        ];

        $item = $this->resolveItem($entity);
        if (null !== $item) {
            $newKey['item'] = $item;
        }

        $bucket['key'] = $newKey;

        return $bucket;
    }

    protected function resolveKey($value): string
    {
        return $value->getId();
    }

    private function loadEntity(string $id)
    {
        return $this->em->find($this->getEntityClass(), $id);
    }

    abstract protected function getEntityClass(): string;
}
