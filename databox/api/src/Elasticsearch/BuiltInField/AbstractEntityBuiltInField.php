<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\KeywordAttributeType;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractEntityBuiltInField extends AbstractBuiltInField
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getType(): string
    {
        return KeywordAttributeType::NAME;
    }

    public function normalizeBuckets(array $buckets): array
    {
        $entities = DoctrineUtil::getIndexFromIds(
            $this->em->getRepository($this->getEntityClass()),
            array_map(fn (array $bucket) => $bucket['key'], $buckets)
        );

        return array_map(function (array $bucket) use ($entities): ?array {
            $entity = $entities[$bucket['key']] ?? null;

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
        }, $buckets);
    }

    protected function resolveKey($value): string
    {
        return $value->getId();
    }

    abstract protected function getEntityClass(): string;
}
