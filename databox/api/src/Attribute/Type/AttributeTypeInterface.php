<?php

declare(strict_types=1);

namespace App\Attribute\Type;

interface AttributeTypeInterface
{
    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function getSearchAnalyzer(string $language): ?string;
}
