<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;
use App\Tests\Functional\Api\Attribute\Hierarchy\GeographyHierarchyTrait;

/**
 * An entity of a hierarchy also finds the assets holding one of its descendants, and its facet nests the
 * entities under their parents.
 */
final class AssetEntityBranchSearchTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;
    use GeographyHierarchyTrait;

    private AttributeDefinition $place;

    private function setUpScene(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['public' => true, 'ownerId' => self::USER]);
        $this->createGeography();
        $this->place = $this->createAttributeDefinition([
            'name' => 'Lieu',
            'slug' => 'lieu',
            'type' => EntityAttributeType::NAME,
            'list' => $this->geo,
            'no_flush' => true,
        ]);
        $this->place->setFacetEnabled(true);

        foreach (['In Paris' => 'paris', 'In Rome' => 'rome', 'In France' => 'france'] as $name => $key) {
            $asset = $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'no_flush' => true,
            ]);
            $attribute = new Attribute();
            $attribute->setAsset($asset);
            $attribute->setDefinition($this->place);
            $attribute->setValue($this->e[$key]->getId());
            $attribute->setOrigin(Attribute::ORIGIN_HUMAN);
            self::getEntityManager()->persist($attribute);
        }
        self::getEntityManager()->flush();

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function testAParentFindsItsDescendants(): void
    {
        $this->setUpScene();

        $this->assertSearch(sprintf('lieu = "%s"', $this->e['france']->getId()), ['In France', 'In Paris']);
        $this->assertSearch(sprintf('lieu IN ("%s", "%s")', $this->e['idf']->getId(), $this->e['latium']->getId()), ['In Paris', 'In Rome']);
        $this->assertSearch(sprintf('lieu = "%s"', $this->e['paris']->getId()), ['In Paris']);
        $this->assertSearch(sprintf('lieu != "%s"', $this->e['france']->getId()), ['In Rome']);
    }

    public function testMovingAnEntityReindexesTheAssetsOfItsBranch(): void
    {
        $this->setUpScene();

        $this->api('PATCH', '/attribute-entities/'.$this->e['idf']->getId(), self::USER, [
            'parent' => '/attribute-entities/'.$this->e['italie']->getId(),
        ]);
        $this->assertResponseIsSuccessful();
        self::waitForESIndex('asset');

        $this->assertSearch(sprintf('lieu = "%s"', $this->e['italie']->getId()), ['In Paris', 'In Rome']);
        $this->assertSearch(sprintf('lieu = "%s"', $this->e['france']->getId()), ['In France']);
    }

    public function testFacetNestsTheEntitiesUnderTheirParent(): void
    {
        $this->setUpScene();

        $data = $this->search([]);
        $buckets = [];
        $facet = array_find($data['facets'], fn (array $f): bool => 'Lieu' === $f['meta']['displayName']);
        foreach ($facet['buckets'] as $bucket) {
            $buckets[$bucket['key']['label']] = [$bucket['doc_count'], $bucket['key']['item']['parentId']];
        }

        $this->assertEquals([
            'Monde' => [3, null],
            'Europe' => [3, $this->e['monde']->getId()],
            'France' => [2, $this->e['europe']->getId()],
            'Île-de-France' => [1, $this->e['france']->getId()],
            'Paris' => [1, $this->e['idf']->getId()],
            'Italie' => [1, $this->e['europe']->getId()],
            'Latium' => [1, $this->e['italie']->getId()],
            'Rome' => [1, $this->e['latium']->getId()],
        ], $buckets);
    }

    /**
     * @param string[] $expectedNames
     */
    private function assertSearch(string $condition, array $expectedNames): void
    {
        $names = array_map(fn (array $asset): ?string => $asset['name'] ?? null, $this->search(['conditions' => [$condition]])['member']);
        sort($names);

        self::assertSame($expectedNames, $names, sprintf('Unexpected results for %s', $condition));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function search(array $query): array
    {
        $response = self::createClient()->request('GET', '/assets', [
            'query' => $query,
        ]);

        return $this->getDataFromResponse($response, 200);
    }
}
