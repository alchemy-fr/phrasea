<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

use App\Attribute\Type\AttributeTypeInterface;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeInterface;

/**
 * A criteria of an AQL condition resolved against one field: the field (and sub-field) the operator
 * applies to, the effective operator and the normalized value, whatever runs it (Elasticsearch or PHP).
 */
final readonly class ResolvedCriteria
{
    /**
     * @param string      $field       the field, its locale resolved ("*" for every locale)
     * @param string|null $subField    the sub-field the operator applies to ("raw", "text"...)
     * @param string|null $rawSubField the sub-field holding the exact value, which the wildcard operators apply to
     * @param string[]    $locales     the locales a "*" field spans, besides the untranslated one
     */
    public function __construct(
        public string $field,
        public ?string $subField,
        public ?string $rawSubField,
        public ConditionOperatorEnum $operator,
        public mixed $value,
        public bool $caseInsensitive,
        public AttributeTypeInterface $type,
        public array $locales,
        public ?BuiltInAttributeInterface $builtInAttribute,
    ) {
    }

    public function getFieldName(): string
    {
        return $this->field.(null !== $this->subField ? '.'.$this->subField : '');
    }

    public function getRawFieldName(): string
    {
        return $this->field.(null !== $this->rawSubField ? '.'.$this->rawSubField : '');
    }
}
