<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Definitions of the same slug, type and cardinality write into the same ES field whatever
 * their workspace: the values of a workspace where the user cannot read the definition must
 * influence no answer (match, facet, sort).
 */
class AttributeWorkspaceVisibilityTest extends AbstractSearchTestCase
{
    private Workspace $readableWorkspace;
    private Workspace $restrictedWorkspace;

    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    private function createWorkspaces(): void
    {
        $this->readableWorkspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $this->restrictedWorkspace = $this->createWorkspace([
            'slug' => 'restricted-workspace',
            'public' => true,
            'no_flush' => true,
        ]);
    }

    private function createRestrictedDefinition(string $name, array $options = []): AttributeDefinition
    {
        return $this->createAttributeDefinition([
            'workspace' => $this->restrictedWorkspace,
            'name' => $name,
            'policy' => $this->createAttributePolicy([
                'workspace' => $this->restrictedWorkspace,
                'name' => 'Private '.$name,
                'public' => false,
                'no_flush' => true,
            ]),
            'no_flush' => true,
            ...$options,
        ]);
    }

    private function createReadableDefinition(Workspace $workspace, string $name, array $options = []): AttributeDefinition
    {
        return $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => $name,
            'policy' => $this->createAttributePolicy([
                'workspace' => $workspace,
                'name' => 'Public '.$name,
                'no_flush' => true,
            ]),
            'no_flush' => true,
            ...$options,
        ]);
    }

    /**
     * @param array<array{AttributeDefinition, mixed}> $values
     */
    private function createPublicAsset(Workspace $workspace, string $name, array $values): Asset
    {
        return $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => true,
            'attributes' => array_map(static fn (array $v): array => [
                'definition' => $v[0],
                'value' => $v[1],
            ], $values),
            'no_flush' => true,
        ]);
    }

    private function searchAssets(array $query): array
    {
        $response = self::createClient()->request('GET', '/assets', ['query' => $query]);

        return $this->getDataFromResponse($response, 200);
    }

    private function searchNames(array $query): array
    {
        return array_column($this->searchAssets($query)['member'], 'name');
    }

    /**
     * A non-searchable definition is indexed too: when the user cannot read it, the searchable
     * definitions sharing its key must not match its values.
     */
    public function testConditionsAndQueryDoNotMatchUnreadableValues(): void
    {
        $this->createWorkspaces();
        $salary = $this->createReadableDefinition($this->readableWorkspace, 'Salary');
        $hiddenSalary = $this->createRestrictedDefinition('Salary', ['searchable' => false]);
        $this->getEntityManager()->flush();

        $this->createPublicAsset($this->readableWorkspace, 'Readable', [[$salary, '1000']]);
        $this->createPublicAsset($this->restrictedWorkspace, 'Hidden', [[$hiddenSalary, '1000']]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $this->assertSame(['Readable'], $this->searchNames(['conditions' => ['salary = "1000"']]));
        $this->assertSame(['Readable'], $this->searchNames(['conditions' => ['salary STARTS WITH "1"']]));
        $this->assertSame(['Readable'], $this->searchNames(['query' => '1000']));
    }

    public function testFacetsDoNotCountUnreadableValues(): void
    {
        $this->createWorkspaces();
        $salary = $this->createReadableDefinition($this->readableWorkspace, 'Salary', ['facet_enabled' => true]);
        $hiddenSalary = $this->createRestrictedDefinition('Salary');
        $city = $this->createReadableDefinition($this->readableWorkspace, 'City', ['facet_enabled' => true]);
        $otherCity = $this->createReadableDefinition($this->restrictedWorkspace, 'City', ['facet_enabled' => true]);
        $this->getEntityManager()->flush();

        $this->createPublicAsset($this->readableWorkspace, 'Readable', [[$salary, '1000'], [$city, 'Paris']]);
        $this->createPublicAsset($this->readableWorkspace, 'Empty', []);
        $hidden = $this->createPublicAsset($this->restrictedWorkspace, 'Hidden', [[$hiddenSalary, '9999'], [$otherCity, 'Rome']]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $facets = $this->searchAssets([])['facets'];
        $this->assertSame(['1000'], array_column($facets['salary_text_s']['buckets'], 'key'));
        $this->assertSame(1, $facets['salary_text_s']['missing_count']);
        $this->assertSame('Salary', $facets['salary_text_s']['meta']['displayName']);
        // Readable in every workspace: no restriction
        $this->assertEqualsCanonicalizing(['Paris', 'Rome'], array_column($facets['city_text_s']['buckets'], 'key'));
        $this->assertSame(1, $facets['city_text_s']['missing_count']);

        $facets = $this->searchAssets(['ids' => [$hidden->getId()]])['facets'];
        $this->assertSame([], $facets['salary_text_s']['buckets']);
        $this->assertSame(0, $facets['salary_text_s']['missing_count']);
        $this->assertSame(['Rome'], array_column($facets['city_text_s']['buckets'], 'key'));
    }

    public function testSortIgnoresUnreadableValues(): void
    {
        $this->createWorkspaces();
        $keys = [
            'salary_text_s' => [null, ['b', 'd'], ['a', 'c', 'e']],
            'rank_number_s' => [NumberAttributeType::NAME, [2, 4], [1, 3, 5]],
            'hired_date-time_s' => [DateTimeAttributeType::NAME, [
                new \DateTimeImmutable('2002-01-01T00:00:00+00:00'),
                new \DateTimeImmutable('2004-01-01T00:00:00+00:00'),
            ], [
                new \DateTimeImmutable('2001-01-01T00:00:00+00:00'),
                new \DateTimeImmutable('2003-01-01T00:00:00+00:00'),
                new \DateTimeImmutable('2005-01-01T00:00:00+00:00'),
            ]],
        ];
        $readableAssets = ['B' => [], 'D' => []];
        $hiddenAssets = ['A' => [], 'C' => [], 'E' => []];
        foreach ($keys as $key => [$type, $readable, $hidden]) {
            $name = ucfirst(explode('_', $key)[0]);
            $options = ['sortable' => true, 'type' => $type ?? TextAttributeType::NAME];
            $readableDefinition = $this->createReadableDefinition($this->readableWorkspace, $name, $options);
            $hiddenDefinition = $this->createRestrictedDefinition($name, $options);
            foreach (array_keys($readableAssets) as $i => $assetName) {
                $readableAssets[$assetName][] = [$readableDefinition, $readable[$i]];
            }
            foreach (array_keys($hiddenAssets) as $i => $assetName) {
                $hiddenAssets[$assetName][] = [$hiddenDefinition, $hidden[$i]];
            }
        }
        $this->getEntityManager()->flush();

        foreach ($readableAssets as $assetName => $values) {
            $this->createPublicAsset($this->readableWorkspace, $assetName, $values);
        }
        foreach ($hiddenAssets as $assetName => $values) {
            $this->createPublicAsset($this->restrictedWorkspace, $assetName, $values);
        }
        $this->getEntityManager()->flush();
        self::releaseIndex();

        foreach (array_keys($keys) as $key) {
            $asc = $this->searchNames(['order' => [$key => 'asc']]);
            $desc = $this->searchNames(['order' => [$key => 'desc']]);

            $this->assertSame(['B', 'D'], array_slice($asc, 0, 2), $key);
            $this->assertSame(['D', 'B'], array_slice($desc, 0, 2), $key);
            // The hidden values sort the other way round in each direction: the order must not follow them
            $this->assertEqualsCanonicalizing(['A', 'C', 'E'], array_slice($asc, 2), $key);
            $this->assertSame(array_slice($asc, 2), array_slice($desc, 2), $key);
        }
    }
}
