<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * A condition comparing a field to something else than a constant becomes a Painless script:
 * it must compile and run in Elasticsearch, including on the assets missing a value it reads.
 */
class AssetScriptConditionSearchTest extends AbstractSearchTestCase
{
    private const array ASSETS = [
        'A' => ['n' => '10', 'm' => '10', 'code' => 'ABC-123', 'label' => 'ABC', 'kw' => 'x1'],
        'B' => ['n' => '10', 'm' => '4', 'code' => 'XYZ-9', 'label' => 'XY', 'kw' => 'x1y'],
        'C' => ['n' => '3', 'm' => '6', 'code' => 'ABD', 'label' => 'ABD!', 'kw' => 'y'],
        'D' => ['n' => '7', 'label' => 'ABC'],
    ];

    public function testScriptConditions(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $definitions = [
            'n' => $this->createDefinition($workspace, 'n', NumberAttributeType::NAME),
            'm' => $this->createDefinition($workspace, 'm', NumberAttributeType::NAME),
            'code' => $this->createDefinition($workspace, 'code', TextAttributeType::NAME),
            'label' => $this->createDefinition($workspace, 'label', TextAttributeType::NAME),
            'kw' => $this->createDefinition($workspace, 'kw', KeywordAttributeType::NAME),
        ];
        self::getEntityManager()->flush();

        foreach (self::ASSETS as $name => $values) {
            $attributes = [];
            foreach ($values as $slug => $value) {
                $attributes[] = [
                    'definition' => $definitions[$slug],
                    'value' => $value,
                ];
            }
            $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'attributes' => $attributes,
                'no_flush' => true,
            ]);
        }
        self::getEntityManager()->flush();
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        // The left operand of a condition is always a field
        $cases = [
            // Comparison operators; D has no "m" and never matches a condition reading it
            'n = m' => ['A'],
            'n != m' => ['B', 'C'],
            'n > m' => ['B'],
            'n <= m' => ['A', 'C'],
            // Arithmetic
            'n >= m * 2' => ['B'],
            'n = m + 6' => ['B'],
            'n = 20 - m' => ['A'],
            // A division of integers gives a decimal number, as with constants: 10 / 4 = 2.5
            'm = n / m * 4 - 6' => ['B'],
            'n = m / 4 * 4' => ['A'],
            // SUBSTRING follows PHP substr(): 0-based, a length, out of range bounds tolerated
            'label = SUBSTRING(code, 0, 3)' => ['A'],
            'label = SUBSTRING(code, 0, -4)' => ['A'],
            'code = SUBSTRING(code, 0, 50)' => ['A', 'B', 'C'],
            'label = CONCAT(SUBSTRING(code, 0, 2), SUBSTRING(label, 2))' => ['A', 'B', 'C'],
            'code = CONCAT(SUBSTRING(code, 0, 4), SUBSTRING(code, -3))' => ['A'],
            // CONCAT, numbers included
            'label = CONCAT(SUBSTRING(code, 0, 3), "!")' => ['C'],
            'kw = CONCAT(SUBSTRING(kw, 0, 2), "y")' => ['B'],
            'kw = CONCAT("x", n - 9)' => ['A'],
            // Nesting
            'n >= m AND label STARTS WITH "AB"' => ['A'],
            // The negation of a script rejecting a missing value keeps the asset, as any NOT
            'NOT (n = m)' => ['B', 'C', 'D'],
        ];

        $failures = [];
        foreach ($cases as $condition => $expectedNames) {
            $names = $this->searchNames($condition);
            if ($names !== $expectedNames) {
                $failures[] = sprintf('"%s": expected %s, got %s', $condition, json_encode($expectedNames), is_array($names) ? json_encode($names) : $names);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    private function createDefinition(Workspace $workspace, string $name, string $type): AttributeDefinition
    {
        return $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => $name,
            'slug' => $name,
            'type' => $type,
            'policy' => $this->createAttributePolicy([
                'workspace' => $workspace,
                'name' => 'Public '.$name,
                'no_flush' => true,
            ]),
            'no_flush' => true,
        ]);
    }

    /**
     * @return string[]|string the names of the assets found, or the error answered
     */
    private function searchNames(string $condition): array|string
    {
        $response = self::createClient()->request('GET', '/assets', [
            'query' => ['conditions' => [$condition]],
        ]);
        if (200 !== $response->getStatusCode()) {
            return sprintf('HTTP %d: %s', $response->getStatusCode(), $response->getContent(false));
        }

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_map(static fn (array $asset): ?string => $asset['name'] ?? null, $data['member']);
        sort($names);

        return $names;
    }
}
