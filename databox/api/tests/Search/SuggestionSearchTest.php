<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Entity\Core\AttributeDefinition;
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

        $values = array_slice($items, 0, 3);
        sort($values);
        $expected = [
            [$keywords->getId(), 'Paris'],
            [$keywords->getId(), 'Parc'],
            [$city->getId(), 'Paris'],
        ];
        sort($expected);
        $this->assertSame($expected, $values);

        foreach ($suggestions as $suggestion) {
            $this->assertStringContainsString('[hl]', $suggestion['hl'], $suggestion['name'].' should be highlighted');
            $this->assertSame($suggestion['name'], preg_replace('#\[/?hl]#', '', $suggestion['hl']));
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

    private function suggest(string $query): array
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets/suggest', [
            'query' => ['query' => $query],
        ]);

        return $this->getDataFromResponse($response, 200)['hydra:member'];
    }
}
