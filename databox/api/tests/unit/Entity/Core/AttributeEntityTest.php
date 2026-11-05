<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Core;

use App\Entity\Core\AttributeEntity;
use PHPUnit\Framework\TestCase;

class AttributeEntityTest extends TestCase
{
    public function testSetSynonymsNormalizesEachList(): void
    {
        $entity = new AttributeEntity();
        $entity->setSynonyms([
            'fr' => [' a ', '', 'a', 'b, c'],
            'en' => [],
            0 => ['x'],
        ]);

        $this->assertSame(['fr' => ['a', 'b, c']], $entity->getSynonyms());
    }

    public function testSetSynonymsReindexesTheLists(): void
    {
        $entity = new AttributeEntity();
        $entity->setSynonyms([
            'fr' => [0 => 'a', 2 => 'b', 3 => '  '],
            'en' => [1 => 'c'],
        ]);

        $synonyms = $entity->getSynonyms();
        $this->assertSame(['fr' => ['a', 'b'], 'en' => ['c']], $synonyms);
        $this->assertTrue(array_is_list($synonyms['fr']));
        $this->assertTrue(array_is_list($synonyms['en']));
        $this->assertSame('{"fr":["a","b"],"en":["c"]}', json_encode($synonyms));
    }

    public function testSetSynonymsDropsListsLeftEmpty(): void
    {
        $entity = new AttributeEntity();
        $entity->setSynonyms(['fr' => ['', ' ']]);

        $this->assertNull($entity->getSynonyms());
    }

    public function testSetSynonymsKeepsNonStringEntriesForTheValidation(): void
    {
        $entity = new AttributeEntity();
        $entity->setSynonyms(['fr' => ['a', 42], 'en' => 'b']);

        $this->assertSame(['fr' => ['a', 42], 'en' => 'b'], $entity->getSynonyms());
    }
}
