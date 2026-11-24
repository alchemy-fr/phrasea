<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Thesaurus;

/**
 * An entity of a tabulated thesaurus.
 */
final readonly class TabulatedThesaurusLine
{
    /**
     * @param int|null                    $parent       index of the parent line in the parsed list; null at the top level
     * @param array<string, string>       $translations locale => term
     * @param array<string, list<string>> $synonyms     locale (NO_LOCALE without default locale) => the terms without
     *                                                  locale after the value
     */
    public function __construct(
        public int $line,
        public ?int $parent,
        public string $value,
        public int $status,
        public ?string $color,
        public array $translations,
        public array $synonyms,
    ) {
    }
}
