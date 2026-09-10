<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;

class SuggestionSearchTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
        self::waitForESIndex('collection');
    }

    public function testAttributeValuesAreSuggestedOncePerDefinition(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $keywords = $this->createSuggestedDefinition($workspace, 'Keywords', ['multiple' => true]);
        $city = $this->createSuggestedDefinition($workspace, 'City');
        $notSuggested = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Comment',
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Party',
            'public' => true,
            'attributes' => [
                ['definition' => $keywords, 'value' => 'Paris'],
                ['definition' => $keywords, 'value' => 'Lyon'],
                ['definition' => $city, 'value' => 'Paris'],
                ['definition' => $notSuggested, 'value' => 'Parade'],
            ],
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Second',
            'public' => true,
            'attributes' => [
                ['definition' => $keywords, 'value' => 'Paris'],
                ['definition' => $keywords, 'value' => 'Parc'],
            ],
            'no_flush' => true,
        ]);
        // Not visible to anonymous users
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Private',
            'attributes' => [
                ['definition' => $keywords, 'value' => 'Parme'],
            ],
            'no_flush' => true,
        ]);
        $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Parking',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $suggestions = $this->suggest('par');

        $items = array_map(fn (array $suggestion): array => [$suggestion['t'], $suggestion['name']], $suggestions);
        // The value shared by the most assets comes first
        $this->assertSame([$keywords->getId(), 'Paris'], $items[0]);
        // Attribute values come before the names
        $this->assertSame([
            ['asset', 'Party'],
            ['collection', 'Parking'],
        ], array_slice($items, 3));

        $this->assertSame(self::sorted([
            [$keywords->getId(), 'Paris'],
            [$keywords->getId(), 'Parc'],
            [$city->getId(), 'Paris'],
        ]), self::sorted(array_slice($items, 0, 3)));

        foreach ($suggestions as $suggestion) {
            $this->assertStringContainsString('[hl]', $suggestion['hl'], $suggestion['name'].' should be highlighted');
            $this->assertSame($suggestion['name'], preg_replace('#\[/?hl]#', '', $suggestion['hl']));
            // Non-translatable definitions are not localized
            $this->assertArrayNotHasKey('locale', $suggestion);
        }
        $this->assertSame('Keywords', $suggestions[0]['tName']);
    }

    public function testAttributeDefinitionPermissionsAreApplied(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $restrictedPolicy = $this->createAttributePolicy([
            'workspace' => $workspace,
            'name' => 'Restricted',
            'public' => false,
            'no_flush' => true,
        ]);
        $restricted = $this->createSuggestedDefinition($workspace, 'Restricted', ['policy' => $restrictedPolicy]);
        $open = $this->createSuggestedDefinition($workspace, 'Open');
        $this->getEntityManager()->flush();

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Asset',
            'public' => true,
            'attributes' => [
                ['definition' => $restricted, 'value' => 'Paradise'],
                ['definition' => $open, 'value' => 'Parasol'],
            ],
        ]);
        self::releaseIndex();

        $suggestions = $this->suggest('par');

        $this->assertSame([
            [$open->getId(), 'Parasol', 'Open'],
        ], array_map(fn (array $suggestion): array => [$suggestion['t'], $suggestion['name'], $suggestion['tName']], $suggestions));
    }

    public function testTranslatableValuesFollowTheDataLocale(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $city = $this->createSuggestedDefinition($workspace, 'City', [
            'translatable' => true,
            'multiple' => true,
        ]);
        $plain = $this->createSuggestedDefinition($workspace, 'Plain');
        $this->getEntityManager()->flush();

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Asset',
            'public' => true,
            'attributes' => [
                ['definition' => $city, 'locale' => 'fr', 'value' => 'Paris'],
                ['definition' => $city, 'locale' => 'en', 'value' => 'Parliament'],
                ['definition' => $city, 'locale' => 'de', 'value' => 'Parkplatz'],
                ['definition' => $city, 'value' => 'Parc'],
                ['definition' => $plain, 'value' => 'Parade'],
            ],
        ]);
        self::releaseIndex();

        // The values of the user locale plus the untranslated ones; non-translatable definitions are not localized
        $this->assertSame(self::sorted([
            [$city->getId(), 'Paris', 'fr'],
            [$city->getId(), 'Parc', 'fr'],
            [$plain->getId(), 'Parade', null],
        ]), $this->suggestedValues('par', ['X-Data-Locale' => 'fr']));

        $expectedEn = self::sorted([
            [$city->getId(), 'Parliament', 'en'],
            [$city->getId(), 'Parc', 'en'],
            [$plain->getId(), 'Parade', null],
        ]);
        $this->assertSame($expectedEn, $this->suggestedValues('par', ['X-Data-Locale' => 'en']));
        // Without a usable locale, the workspace fallback locale (en) applies
        $this->assertSame($expectedEn, $this->suggestedValues('par'));
        $this->assertSame($expectedEn, $this->suggestedValues('par', ['X-Data-Locale' => 'it']));
    }

    public function testEntityLabelsAreSuggestedInTheDataLocale(): void
    {
        $em = $this->getEntityManager();
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $list = new EntityList();
        $list->setName('Places');
        $list->setWorkspace($workspace);
        $em->persist($list);
        $parliament = $this->createEntity($list, 'Parliament', ['fr' => 'Parlement']);
        $parc = $this->createEntity($list, 'Parc');
        $zoo = $this->createEntity($list, 'Zoo', ['fr' => 'Parc zoologique']);
        $place = $this->createSuggestedDefinition($workspace, 'Place', [
            'type' => EntityAttributeType::getName(),
            'list' => $list,
            'multiple' => true,
        ]);
        $em->flush();

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Asset',
            'public' => true,
            'attributes' => [
                ['definition' => $place, 'value' => $parliament->getId()],
                ['definition' => $place, 'value' => $parc->getId()],
                ['definition' => $place, 'value' => $zoo->getId()],
            ],
        ]);
        self::releaseIndex();

        // One label per entity: its translation for the user locale, or its base label
        $this->assertSame(self::sorted([
            [$place->getId(), 'Parlement', 'fr'],
            [$place->getId(), 'Parc', 'fr'],
            [$place->getId(), 'Parc zoologique', 'fr'],
        ]), $this->suggestedValues('par', ['X-Data-Locale' => 'fr']));
        $this->assertSame(self::sorted([
            [$place->getId(), 'Parliament', 'en'],
            [$place->getId(), 'Parc', 'en'],
        ]), $this->suggestedValues('par', ['X-Data-Locale' => 'en']));
    }

    private function createSuggestedDefinition(Workspace $workspace, string $name, array $options = []): AttributeDefinition
    {
        $definition = $this->createAttributeDefinition(array_merge([
            'workspace' => $workspace,
            'name' => $name,
            'no_flush' => true,
        ], $options));
        $definition->setSuggest(true);

        return $definition;
    }

    /**
     * @param array<string, string> $translations
     */
    private function createEntity(EntityList $list, string $value, array $translations = []): AttributeEntity
    {
        $entity = new AttributeEntity();
        $entity->setList($list);
        $entity->setValue($value);
        if (!empty($translations)) {
            $entity->setTranslations($translations);
        }
        $this->getEntityManager()->persist($entity);

        return $entity;
    }

    private function suggest(string $query, array $headers = []): array
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets/suggest', [
            'query' => ['query' => $query],
            'headers' => $headers,
        ]);

        return $this->getDataFromResponse($response, 200)['hydra:member'];
    }

    /**
     * @return array<array{0: string, 1: string, 2: ?string}> [definition ID, value, locale] of the attribute suggestions, sorted
     */
    private function suggestedValues(string $query, array $headers = []): array
    {
        $suggestions = array_filter(
            $this->suggest($query, $headers),
            fn (array $suggestion): bool => !in_array($suggestion['t'], ['asset', 'collection'], true),
        );

        return self::sorted(array_map(
            fn (array $suggestion): array => [$suggestion['t'], $suggestion['name'], $suggestion['locale'] ?? null],
            $suggestions,
        ));
    }

    private static function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }
}
