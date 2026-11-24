<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AttributeEntity;

use App\Entity\Core\AttributeEntity;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurus;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurusLine;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurusSyntaxException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TabulatedThesaurusTest extends TestCase
{
    private const string EXAMPLE = <<<'TXT'
        animal
            mammifère [fr]; mammal [en]
                {#ff0000} chien [fr] ; dog [en]
                ! {#00F800} cat [en]; chat [fr]
            oiseau [fr]
                ? pigeon
        object [en] ; objet [fr]
            table
            chaise [fr]
        TXT;

    public function testParsesTheExample(): void
    {
        $lines = TabulatedThesaurus::parse(self::EXAMPLE);

        $this->assertSame(
            [
                // value, parent value, status, color, translations
                ['animal', null, AttributeEntity::STATUS_APPROVED, null, []],
                ['mammifère', 'animal', AttributeEntity::STATUS_APPROVED, null, ['fr' => 'mammifère', 'en' => 'mammal']],
                ['chien', 'mammifère', AttributeEntity::STATUS_APPROVED, '#ff0000', ['fr' => 'chien', 'en' => 'dog']],
                ['cat', 'mammifère', AttributeEntity::STATUS_REJECTED, '#00f800', ['en' => 'cat', 'fr' => 'chat']],
                ['oiseau', 'animal', AttributeEntity::STATUS_APPROVED, null, ['fr' => 'oiseau']],
                ['pigeon', 'oiseau', AttributeEntity::STATUS_PENDING, null, []],
                ['object', null, AttributeEntity::STATUS_APPROVED, null, ['en' => 'object', 'fr' => 'objet']],
                ['table', 'object', AttributeEntity::STATUS_APPROVED, null, []],
                ['chaise', 'object', AttributeEntity::STATUS_APPROVED, null, ['fr' => 'chaise']],
            ],
            array_map(fn (TabulatedThesaurusLine $l): array => [
                $l->value,
                null !== $l->parent ? $lines[$l->parent]->value : null,
                $l->status,
                $l->color,
                $l->translations,
            ], $lines),
        );
    }

    public function testTheValueIsTheFirstTermWithoutLocale(): void
    {
        [$line] = TabulatedThesaurus::parse('chien [fr]; Dog; toutou; dog [en]');

        $this->assertSame('Dog', $line->value);
        $this->assertSame(['_' => ['toutou']], $line->synonyms);
        $this->assertSame(['fr' => 'chien', 'en' => 'dog'], $line->translations);
    }

    public function testTermsWithoutLocaleTakeTheDefaultLocale(): void
    {
        [$line] = TabulatedThesaurus::parse('Chien; toutou; dog [en]', 'FR');

        $this->assertSame('Chien', $line->value);
        $this->assertSame(['fr' => 'Chien', 'en' => 'dog'], $line->translations);
        $this->assertSame(['fr' => ['toutou']], $line->synonyms);

        [$tagged] = TabulatedThesaurus::parse('chien [fr]; dog [en]', 'fr');
        $this->assertSame('chien', $tagged->value);
        $this->assertSame([], $tagged->synonyms);
    }

    public function testTheNextTermsInALocaleAreSynonyms(): void
    {
        [$line] = TabulatedThesaurus::parse('chien [fr]; dog [en]; toutou [fr]; clébard [FR]; chien [fr]');

        $this->assertSame('chien', $line->value);
        $this->assertSame(['fr' => 'chien', 'en' => 'dog'], $line->translations);
        $this->assertSame(['fr' => ['toutou', 'clébard']], $line->synonyms);

        [$default] = TabulatedThesaurus::parse('chien; clébard [fr]', 'fr');
        $this->assertSame(['fr' => 'chien'], $default->translations);
        $this->assertSame(['fr' => ['clébard']], $default->synonyms);

        [$after] = TabulatedThesaurus::parse('clébard [fr]; chien', 'fr');
        $this->assertSame('chien', $after->value);
        $this->assertSame(['fr' => 'clébard'], $after->translations);
        $this->assertSame(['fr' => ['chien']], $after->synonyms);
    }

    public function testTabsIndentToo(): void
    {
        $lines = TabulatedThesaurus::parse("a\n\tb\n\t\tc\n\td\ne");

        $this->assertSame([null, 0, 1, 0, null], array_map(fn (TabulatedThesaurusLine $l): ?int => $l->parent, $lines));
    }

    /**
     * @return iterable<string, array{string, list<array{line: int, message: string}>}>
     */
    public static function syntaxErrors(): iterable
    {
        yield 'several colors' => ["a\n    {#ff0000} {#00ff00} b", [['line' => 2, 'message' => 'Several colors']]];
        yield 'invalid color' => ['{#red} a', [['line' => 1, 'message' => 'Invalid color "#red" (expected #rrggbb)']]];
        yield 'no term' => ['? {#ff0000}', [['line' => 1, 'message' => 'No term']]];
        yield 'unmatched indentation' => ["a\n        b\n    c", [['line' => 3, 'message' => 'The indentation does not match any upper level']]];
        yield 'indented first line' => ['    a', [['line' => 1, 'message' => 'The indentation does not match any upper level']]];
        yield 'every error' => ["{#000000}{#ffffff} a\n    ?", [
            ['line' => 1, 'message' => 'Several colors'],
            ['line' => 2, 'message' => 'No term'],
        ]];
    }

    /**
     * @param list<array{line: int, message: string}> $expected
     */
    #[DataProvider('syntaxErrors')]
    public function testSyntaxErrors(string $data, array $expected): void
    {
        try {
            TabulatedThesaurus::parse($data);
            $this->fail('A syntax error was expected');
        } catch (TabulatedThesaurusSyntaxException $e) {
            $this->assertSame($expected, $e->getErrors());
        }
    }

    public function testFormattedLinesAreReadBack(): void
    {
        $text = implode("\n", [
            TabulatedThesaurus::formatLine(0, AttributeEntity::STATUS_APPROVED, null, 'animal', [], []),
            TabulatedThesaurus::formatLine(1, AttributeEntity::STATUS_APPROVED, null, 'mammifère', ['en' => 'mammal', 'fr' => 'mammifère'], []),
            TabulatedThesaurus::formatLine(2, AttributeEntity::STATUS_REJECTED, '#00f800', 'cat', ['en' => 'cat', 'fr' => 'chat'], []),
            TabulatedThesaurus::formatLine(2, AttributeEntity::STATUS_PENDING, null, 'Dog', ['fr' => 'chien'], ['_' => ['toutou'], 'fr' => ['clébard']]),
            TabulatedThesaurus::formatLine(2, AttributeEntity::STATUS_APPROVED, null, 'souris', ['en' => 'mouse', 'fr' => 'souris'], ['fr' => ['mulot']]),
        ]);

        $this->assertSame(
            "animal\n\tmammifère [fr]; mammal [en]\n\t\t! {#00f800} cat [en]; chat [fr]\n\t\t? Dog; toutou; chien [fr]; clébard [fr]\n\t\tsouris [fr]; mouse [en]; mulot [fr]",
            $text,
        );

        $lines = TabulatedThesaurus::parse($text);
        $this->assertSame(['animal', 'mammifère', 'cat', 'Dog', 'souris'], array_map(fn (TabulatedThesaurusLine $l): string => $l->value, $lines));
        $this->assertSame([null, 0, 1, 1, 1], array_map(fn (TabulatedThesaurusLine $l): ?int => $l->parent, $lines));
        $this->assertSame(['_' => ['toutou'], 'fr' => ['clébard']], $lines[3]->synonyms);
        $this->assertSame(['fr' => 'souris', 'en' => 'mouse'], $lines[4]->translations);
        $this->assertSame(['fr' => ['mulot']], $lines[4]->synonyms);
    }
}
