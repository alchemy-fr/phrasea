<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /attributes?definition=…: the values of a definition across the assets.
 */
class AttributeCollectionByDefinitionTest extends AbstractDataboxTestCase
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

        // Without a definition, an asset is still required
        $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(400);

        $query = fn (array $query): array => array_column($this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => $query])->toArray()['member'], 'id');

        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId(), $hidden->getId()], $query(['definition' => $definition->getId()]));
        $this->assertEqualsCanonicalizing([$human->getId(), $hidden->getId()], $query(['definition' => $definition->getId(), 'value' => 'sea']));
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId(), $hidden->getId()], $query(['definition' => $definition->getId(), 'value' => ['sea', 'sky']]));
        $this->assertSame([$machine->getId()], $query(['definition' => $definition->getId(), 'origin' => 'machine']));
        $this->assertEqualsCanonicalizing([$human->getId(), $hidden->getId(), $other->getId()], $query(['definition' => [$definition->getId(), $otherDefinition->getId()], 'value' => 'sea']));
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId()], $query(['definition' => $definition->getId(), 'asset' => $publicAsset->getId()]));

        $response = $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['definition' => $definition->getId(), 'limit' => 2]]);
        $this->assertCount(2, $response->toArray()['member']);
        $this->assertSame(3, $response->toArray()['totalItems']);

        $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['definition' => $definition->getId(), 'origin' => 'nope']]);
        $this->assertResponseStatusCodeSame(422);

        // A mere reader only gets the values of the assets they can read
        $response = $this->api('GET', '/attributes', KeycloakClientTestMock::USER_UID, options: ['query' => ['definition' => $definition->getId()]]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertEqualsCanonicalizing([$human->getId(), $machine->getId()], array_column($response->toArray()['member'], 'id'));

        // A private attribute policy hides the definition
        $this->api('GET', '/attributes', KeycloakClientTestMock::USER_UID, options: ['query' => ['definition' => $privateDefinition->getId()]]);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/attributes', KeycloakClientTestMock::USER_UID, options: ['query' => ['definition' => [$definition->getId(), $privateDefinition->getId()]]]);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['definition' => $privateDefinition->getId()]]);
        $this->assertResponseStatusCodeSame(200);

        $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['definition' => '00000000-0000-0000-0000-000000000000']]);
        $this->assertResponseStatusCodeSame(404);
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
