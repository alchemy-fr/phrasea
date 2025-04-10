<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CollectionPathAttributeType extends AbstractAttributeType
{
    public const string NAME = 'collection_path';

    public function __construct(
        private readonly EntityManagerInterface $em,
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

    public function isListed(): bool
    {
        return false;
    }
}
