<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class KeywordAttributeType extends AbstractAttributeType
{
    public const string NAME = 'keyword';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'keyword';
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Keyword;
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }

    public function isLocaleAware(): bool
    {
        return true;
    }

    public function supportsSuggest(): bool
    {
        return true;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            $context->addViolation('Invalid text value');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
