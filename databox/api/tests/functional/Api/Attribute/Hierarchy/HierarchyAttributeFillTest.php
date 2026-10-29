<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute\Hierarchy;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Upward filling of the attributes bound to the depths of an entity list hierarchy.
 */
final class HierarchyAttributeFillTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;
    use GeographyHierarchyTrait;

    private Workspace $workspace;
    private Asset $asset;

    /**
     * @param array<string, bool> $multiple
     */
    private function setUpScene(array $multiple = []): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->createGeography();
        $this->createGeographyDefinitions($multiple);
        $this->asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::USER]);
    }

    /**
     * @param array<string, string|string[]|null> $values definition key => entity key(s)
     */
    private function set(array $values, ?string $strategy = null): ResponseInterface
    {
        $actions = [];
        foreach ($values as $key => $value) {
            $actions[] = [
                'action' => 'set',
                'definitionId' => $this->defs[$key]->getId(),
                'origin' => 'human',
                'value' => \is_array($value)
                    ? array_map(fn (string $k): string => $this->e[$k]->getId(), $value)
                    : (null !== $value ? $this->e[$value]->getId() : null),
            ];
        }

        return $this->api('POST', '/assets/'.$this->asset->getId().'/attributes', self::USER, array_filter([
            'actions' => $actions,
            'hierarchyConflict' => $strategy,
        ]));
    }

    /**
     * @return array<string, array<string, string>> definition key => entity value => origin label
     */
    private function values(): array
    {
        $em = self::getEntityManager();
        $em->clear();

        /** @var Attribute[] $attributes */
        $attributes = $em->getRepository(Attribute::class)->findBy(['asset' => $this->asset->getId()]);
        $keys = array_flip(array_map(fn ($d): string => $d->getId(), $this->defs));
        $byId = [];
        foreach ($this->e as $entity) {
            $byId[$entity->getId()] = $entity->getValue();
        }

        $values = [];
        foreach ($attributes as $attribute) {
            $values[$keys[$attribute->getDefinition()->getId()]][$byId[$attribute->getValue()]] = $attribute->getOriginLabel();
        }
        ksort($values);

        return $values;
    }

    public function testDeepestValueFillsEveryAncestorLevel(): void
    {
        $this->setUpScene();

        $this->set(['rue' => 'rvh_paris']);
        $this->assertResponseIsSuccessful();

        $this->assertSame([
            'arrondissement' => ['16e' => 'hierarchy'],
            'continent' => ['Europe' => 'hierarchy'],
            'pays' => ['France' => 'hierarchy'],
            'region' => ['Île-de-France' => 'hierarchy'],
            'rue' => ['Rue Victor Hugo' => 'human'],
            'ville' => ['Paris' => 'hierarchy'],
        ], $this->values());
    }

    public function testEmptyLevelStaysEmpty(): void
    {
        $this->setUpScene();

        $this->set(['rue' => 'rvh_tours']);
        $this->assertResponseIsSuccessful();

        $values = $this->values();
        $this->assertArrayNotHasKey('arrondissement', $values);
        $this->assertSame(['Tours' => 'hierarchy'], $values['ville']);
        $this->assertSame(['Indre-et-Loire' => 'hierarchy'], $values['region']);
    }

    public function testGeneralLevelAloneFillsOnlyItsAncestors(): void
    {
        $this->setUpScene();

        $this->set(['pays' => 'france']);
        $this->assertResponseIsSuccessful();

        $this->assertSame([
            'continent' => ['Europe' => 'hierarchy'],
            'pays' => ['France' => 'human'],
        ], $this->values());
    }

    public function testFilledValuesFollowTheSource(): void
    {
        $this->setUpScene();

        $this->set(['ville' => 'paris']);
        $this->set(['ville' => 'tours']);
        $this->assertResponseIsSuccessful();

        $values = $this->values();
        $this->assertSame(['Indre-et-Loire' => 'hierarchy'], $values['region']);
        $this->assertSame(['France' => 'hierarchy'], $values['pays']);

        $this->set(['ville' => null]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->values());
    }

    public function testMultipleSourcesFillMultipleParents(): void
    {
        $this->setUpScene(['ville' => true, 'pays' => true, 'region' => true]);

        $this->set(['ville' => ['paris', 'rome']]);
        $this->assertResponseIsSuccessful();

        $values = $this->values();
        $this->assertEqualsCanonicalizing(['France', 'Italie'], array_keys($values['pays']));
        $this->assertSame(['Europe' => 'hierarchy'], $values['continent']);
    }

    public function testSingleValuedParentRefusesSeveralExpectedParents(): void
    {
        $this->setUpScene(['ville' => true]);

        $this->set(['ville' => ['paris', 'rome']], 'replace');
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([], $this->values());
    }

    public function testManualValueIsNeverOverwrittenByDefault(): void
    {
        $this->setUpScene();
        $this->set(['pays' => 'belgique']);

        $response = $this->set(['ville' => 'paris']);
        $this->assertResponseStatusCodeSame(422);
        $violations = $response->toArray(false)['violations'];
        $this->assertSame('hierarchy.'.$this->defs['pays']->getId(), $violations[0]['propertyPath']);
        $this->assertSame('hierarchy_conflict', $violations[0]['code']);

        // The whole batch is rolled back
        $this->assertSame([
            'continent' => ['Europe' => 'hierarchy'],
            'pays' => ['Belgique' => 'human'],
        ], $this->values());
    }

    public function testReplaceLetsTheNewDeeperValueWin(): void
    {
        $this->setUpScene();
        $this->set(['pays' => 'belgique']);

        $this->set(['ville' => 'paris'], 'replace');
        $this->assertResponseIsSuccessful();

        $values = $this->values();
        $this->assertSame(['France' => 'hierarchy'], $values['pays']);
        $this->assertSame(['Paris' => 'human'], $values['ville']);
    }

    public function testChangingAParentLevelRemovesIncompatibleChildrenOnReplace(): void
    {
        $this->setUpScene();
        $this->set(['region' => 'idf', 'ville' => 'paris']);

        $this->set(['pays' => 'italie']);
        $this->assertResponseStatusCodeSame(422);

        $this->set(['pays' => 'italie'], 'replace');
        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'continent' => ['Europe' => 'hierarchy'],
            'pays' => ['Italie' => 'human'],
        ], $this->values());
    }

    public function testValueMustBeAtTheLevelOfTheAttribute(): void
    {
        $this->setUpScene();

        $this->set(['pays' => 'paris']);
        $this->assertResponseStatusCodeSame(422);

        $this->set(['arrondissement' => 'tours_none']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testMovingABranchRecomputesOnlyFilledValues(): void
    {
        $this->setUpScene();
        $this->set(['ville' => 'tours']);
        $other = $this->asset;
        $this->asset = $this->createAsset([
            'workspace' => self::getEntityManager()->find(Workspace::class, $this->workspace->getId()),
            'ownerId' => self::USER,
        ]);
        $this->set(['ville' => 'tours', 'region' => 'indre']);
        $manualAsset = $this->asset;

        // Tours moves to Île-de-France
        $this->api('PATCH', '/attribute-entities/'.$this->e['tours']->getId(), self::USER, [
            'parent' => '/attribute-entities/'.$this->e['idf']->getId(),
        ]);
        $this->assertResponseIsSuccessful();

        $this->asset = $other;
        $this->assertSame(['Île-de-France' => 'hierarchy'], $this->values()['region']);

        // The human value is kept (and the conflict reported)
        $this->asset = $manualAsset;
        $this->assertSame(['Indre-et-Loire' => 'human'], $this->values()['region']);
    }
}
