<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * GET /attributes?definition=…: the values of a definition across the readable assets,
 * found in the asset index and paginated by asset.
 */
class AttributeCollectionByDefinitionTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;

    public function testListsTheValuesOfADefinitionAcrossTheReadableAssets(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $workspace->getId());
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Keywords', 'slug' => 'keywords', 'multiple' => true]);
        $otherDefinition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Other', 'slug' => 'other', 'multiple' => true]);
        $privatePolicy = $this->createAttributePolicy(['workspace' => $workspace, 'name' => 'Private', 'public' => false]);
        $privateDefinition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Private', 'slug' => 'private', 'multiple' => true, 'policy' => $privatePolicy]);

        $publicAsset = $this->createAsset(['workspace' => $workspace, 'public' => true]);
        $privateAsset = $this->createAsset(['workspace' => $workspace]);

        $human = $this->createValue($publicAsset, $definition, 'sea', Attribute::ORIGIN_HUMAN);
        $machine = $this->createValue($publicAsset, $definition, 'sky', Attribute::ORIGIN_MACHINE);
        $hidden = $this->createValue($privateAsset, $definition, 'sea', Attribute::ORIGIN_HUMAN);
        $other = $this->createValue($publicAsset, $otherDefinition, 'sea', Attribute::ORIGIN_HUMAN);
        $em->flush();
        self::populateSearchIndices();

        // Without a definition, an asset is still required
        $this->api('GET', '/attributes', self::ADMIN);
        $this->assertResponseStatusCodeSame(400);

        $query = fn (array $query, string $userId = self::ADMIN): array => $this->api('GET', '/attributes', $userId, options: ['query' => $query])->toArray();

        $data = $query(['definition' => $definition->getId()]);
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId(), $hidden->getId()], array_column($data['member'], 'id'));
        $this->assertSame(2, $data['totalItems']);

        $data = $query(['definition' => $definition->getId(), 'value' => 'sea']);
        $this->assertEqualsCanonicalizing([$human->getId(), $hidden->getId()], array_column($data['member'], 'id'));
        $this->assertSame(2, $data['totalItems']);

        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId(), $hidden->getId()], array_column($query(['definition' => $definition->getId(), 'value' => ['sea', 'sky']])['member'], 'id'));

        $data = $query(['definition' => $definition->getId(), 'origin' => 'machine']);
        $this->assertSame([$machine->getId()], array_column($data['member'], 'id'));
        $this->assertSame(1, $data['totalItems']);

        $this->assertEqualsCanonicalizing([$human->getId(), $hidden->getId(), $other->getId()], array_column($query(['definition' => [$definition->getId(), $otherDefinition->getId()], 'value' => 'sea'])['member'], 'id'));

        $data = $query(['definition' => $definition->getId(), 'asset' => $publicAsset->getId()]);
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId()], array_column($data['member'], 'id'));
        $this->assertSame(1, $data['totalItems']);

        // The pages hold assets: every value of the asset of the page, whatever the limit
        $publicValues = [$human->getId(), $machine->getId()];
        sort($publicValues);
        $byAsset = [$publicValues, [$hidden->getId()]];
        $pages = [];
        foreach ([1, 2] as $page) {
            $data = $query(['definition' => $definition->getId(), 'limit' => 1, 'page' => $page]);
            $this->assertSame(2, $data['totalItems']);
            $pages[] = $ids = array_column($data['member'], 'id');
            sort($ids);
            $this->assertContains($ids, $byAsset);
        }
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId(), $hidden->getId()], array_merge(...$pages));
        $this->assertSame([], $query(['definition' => $definition->getId(), 'limit' => 1, 'page' => 3])['member']);

        $this->api('GET', '/attributes', self::ADMIN, options: ['query' => ['definition' => $definition->getId(), 'origin' => 'nope']]);
        $this->assertResponseStatusCodeSame(422);

        // A mere reader only gets, and counts, the values of the assets they can read
        $data = $query(['definition' => $definition->getId()], self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId()], array_column($data['member'], 'id'));
        $this->assertSame(1, $data['totalItems']);

        $data = $query(['definition' => $definition->getId(), 'value' => 'sea'], self::USER);
        $this->assertSame([$human->getId()], array_column($data['member'], 'id'));
        $this->assertSame(1, $data['totalItems']);

        $data = $query(['definition' => $definition->getId(), 'asset' => $privateAsset->getId()], self::USER);
        $this->assertSame([], $data['member']);
        $this->assertSame(0, $data['totalItems']);

        // A private attribute policy hides the definition
        $this->api('GET', '/attributes', self::USER, options: ['query' => ['definition' => $privateDefinition->getId()]]);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/attributes', self::USER, options: ['query' => ['definition' => [$definition->getId(), $privateDefinition->getId()]]]);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/attributes', self::ADMIN, options: ['query' => ['definition' => $privateDefinition->getId()]]);
        $this->assertResponseStatusCodeSame(200);

        $this->api('GET', '/attributes', self::ADMIN, options: ['query' => ['definition' => '00000000-0000-0000-0000-000000000000']]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFindsThePendingEntitiesAndLeavesOutTheDeletedAndQuarantinedAssets(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $list = $this->createEntityList(['name' => 'Persons', 'workspace' => $workspace]);
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Persons', 'slug' => 'persons', 'multiple' => true, 'type' => EntityAttributeType::NAME, 'list' => $list]);
        $unknown = $this->createEntity($list, 'Unknown person · a1b2', ['status' => AttributeEntity::STATUS_PENDING]);

        $asset = $this->createAsset(['workspace' => $workspace]);
        $deleted = $this->createAsset(['workspace' => $workspace]);
        $quarantined = $this->createAsset(['workspace' => $workspace]);
        $pending = $this->createValue($asset, $definition, $unknown->getId(), Attribute::ORIGIN_MACHINE);
        $this->createValue($deleted, $definition, $unknown->getId(), Attribute::ORIGIN_MACHINE);
        $this->createValue($quarantined, $definition, $unknown->getId(), Attribute::ORIGIN_MACHINE);
        $deleted->setDeletedAt(new \DateTimeImmutable());
        $quarantined->setStatus(AssetStatusEnum::Quarantined);
        $em->flush();
        self::populateSearchIndices();

        $data = $this->api('GET', '/attributes', self::ADMIN, options: ['query' => ['definition' => $definition->getId(), 'value' => $unknown->getId()]])->toArray();
        $this->assertSame([$pending->getId()], array_column($data['member'], 'id'));
        $this->assertSame(1, $data['totalItems']);
    }

    private function createValue(Asset $asset, AttributeDefinition $definition, string $value, int $origin): Attribute
    {
        $attribute = new Attribute();
        $attribute->setAsset($asset);
        $attribute->setDefinition($definition);
        $attribute->setValue($value);
        $attribute->setOrigin($origin);
        self::getEntityManager()->persist($attribute);

        return $attribute;
    }
}
