<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

interface AttributeTypeInterface
{
    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function supportsAggregation(): bool;

    public function getElasticSearchMapping(string $language): array;

    /**
     * Normalize value for Elastic search.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalizeValue($value);

    /**
     * De-normalize value from Elastic search to PHP.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function denormalizeValue($value);

    public function isLocaleAware(): bool;

    public function validate($value, ExecutionContextInterface $context): void;
}
