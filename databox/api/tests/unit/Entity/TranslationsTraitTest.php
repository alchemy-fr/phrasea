<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Core\Collection;
use PHPUnit\Framework\TestCase;

class TranslationsTraitTest extends TestCase
{
    public function testStoredLocalesAreNormalized(): void
    {
        $collection = self::createCollection(['en-US' => 'A']);

        $this->assertSame(['en_US' => 'A'], $collection->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME));
        $this->assertSame('A', $collection->getTranslatedField(Collection::TR_FIELD_NAME, ['en_US'], 'Name'));
        $this->assertSame('A', $collection->getTranslatedField(Collection::TR_FIELD_NAME, ['en-US'], 'Name'));
        $this->assertSame('A', $collection->getTranslatedField(Collection::TR_FIELD_NAME, ['en'], 'Name'));
    }

    public function testNormalizedLocaleWins(): void
    {
        $this->assertSame(['en_US' => 'A'], self::createCollection(['en_US' => 'A', 'en-US' => 'B'])->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME));
        $this->assertSame(['en_US' => 'A'], self::createCollection(['en-US' => 'B', 'en_US' => 'A'])->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME));
        $this->assertSame(['en_US' => 'B'], self::createCollection(['en-US' => 'B', 'EN-us' => 'C'])->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME));
    }

    public function testOrderIsKept(): void
    {
        $collection = self::createCollection(['fr' => 'Bonjour', 'de-DE' => 'Hallo', 'en_GB' => 'Hello', 'de_DE' => 'Guten Tag']);

        $this->assertSame(
            ['fr' => 'Bonjour', 'de_DE' => 'Guten Tag', 'en_GB' => 'Hello'],
            $collection->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME),
        );
    }

    public function testFallback(): void
    {
        $this->assertSame([], (new Collection())->getFieldTranslationsByLocale(Collection::TR_FIELD_NAME));
        $this->assertSame('Name', self::createCollection(['fr' => 'Nom'])->getTranslatedField(Collection::TR_FIELD_NAME, ['en'], 'Name'));
        $this->assertSame('Nom', self::createCollection(['fr' => 'Nom'])->getTranslatedField(Collection::TR_FIELD_NAME, ['fr_CA', 'en'], 'Name'));
    }

    public function testSortNames(): void
    {
        $collection = self::createCollection(['fr' => 'Été 2', 'en-US' => 'Summer 10', 'de' => 'Sommer']);

        $sep = Collection::SORT_SEP;
        $this->assertSame([
            '0000'.$sep.'fr'.$sep.'ete 012',
            '0001'.$sep.'en_US'.$sep.'summer 0210',
            '0002'.$sep.'de'.$sep.'sommer',
        ], $collection->getSortNames());
        $this->assertSame([], (new Collection())->getSortNames());
    }

    private static function createCollection(array $nameTranslations): Collection
    {
        $collection = new Collection();
        $collection->setName('Name');
        $collection->setTranslations([Collection::TR_FIELD_NAME => $nameTranslations]);

        return $collection;
    }
}
