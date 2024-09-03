<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CollectionPathAttributeType extends AbstractAttributeType
{
    public const NAME = 'collection_path';

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        if (is_array($value)) {
            $value = array_map(function (string $v): string {
                $ids = explode('/', preg_replace('#^/#', '', $v));
                // Retrieve parents IDs if filter is sent from user who has access to a sub hierarchy only
                $firstColl = array_shift($ids);
                $collection = $this->em->find(Collection::class, $firstColl);
                if ($collection instanceof Collection) {
                    $parents = [];
                    $pColl = $collection;
                    while ($pColl) {
                        $parents[] = $pColl->getId();
                        $pColl = $pColl->getParent();
                    }

                    return '/'.implode('/', array_merge(array_reverse($parents), $ids));
                }

                return $v;
            }, $value);
        }

        return new Query\Terms($field, $value);
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return null;
    }

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        throw new \LogicException('Should never be called');
    }

    public function getAggregationField(): ?string
    {
        throw new \LogicException('Should never be called');
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
