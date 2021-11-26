<?php

declare(strict_types=1);

namespace App\Attribute\Type;

interface AttributeTypeInterface
{
    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function getSearchAnalyzer(string $language): ?string;

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
}
