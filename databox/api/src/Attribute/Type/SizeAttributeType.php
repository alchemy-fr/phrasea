<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SizeAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'size';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return [
            'fields' => [
                AttributeTypeInterface::RAW_PROP => [
                    'type' => 'keyword',
                ],
            ],
        ];
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function getElasticSearchType(): string
    {
        return 'long';
    }

    public function supportsSuggest(): bool
    {
        return true;
    }

    /**
     * @param int|string $value
     *
     * @return int
     */
    public function normalizeElasticsearchValue($value)
    {
        return (int) $value;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_int($value)) {
            $context->addViolation('Invalid size (bytes)');
        }
    }

    public function denormalizeValue(?string $value)
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int
        }

        return $value;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
