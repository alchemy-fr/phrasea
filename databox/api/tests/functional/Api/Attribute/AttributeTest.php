<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Attribute\Type\IpAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /attributes: the attribute values of an asset, one row per value (and per locale).
 */
final class AttributeTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private Asset $asset;
    private AttributeDefinition $title;

    /**
     * A workspace where USER and OTHER are members; the asset belongs to USER
     * and is public, so OTHER can read it but cannot edit its attributes.
     */
    private function setUpScene(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $this->addUserOnWorkspace(self::USER, $workspace->getId());
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());

        $this->asset = $this->createAsset([
            'ownerId' => self::USER,
            'public' => true,
        ]);
        $this->title = $this->createAttributeDefinition(['name' => 'Title']);
    }

    private function createAttribute(AttributeDefinition $definition, string $value, array $options = []): Attribute
    {
        $attribute = new Attribute();
        $attribute->setAsset($this->findAsset($this->asset->getId()));
        $attribute->setDefinition($definition);
        $attribute->setValue($value);
        $attribute->setLocale($options['locale'] ?? null);
        $attribute->setPosition($options['position'] ?? 0);
        $attribute->setOrigin(Attribute::ORIGIN_HUMAN);

        $em = self::getEntityManager();
        $em->persist($attribute);
        $em->flush();

        return $attribute;
    }

    private function postAttribute(?string $userId, AttributeDefinition $definition, mixed $value, array $extra = []): array
    {
        $response = $this->api('POST', '/attributes', $userId, array_merge([
            'asset' => '/assets/'.$this->asset->getId(),
            'definitionId' => $definition->getId(),
            'value' => $value,
        ], $extra));

        return $response->toArray(false);
    }

    private function listValues(string $userId): array
    {
        $response = $this->api('GET', '/attributes', $userId, options: [
            'query' => ['assetId' => $this->asset->getId()],
        ]);
        $this->assertResponseStatusCodeSame(200);

        return array_map(fn (array $a): string => (string) $a['value'], $response->toArray()['hydra:member']);
    }

    private function createSecretPolicy(): AttributePolicy
    {
        return $this->createAttributePolicy([
            'name' => 'Secret',
            'public' => false,
            'editable' => true,
        ]);
    }

    public function testListRequiresAnAssetId(): void
    {
        $this->setUpScene();

        $this->api('GET', '/attributes', self::USER);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testListOfAnUnknownAssetIsNotFound(): void
    {
        $this->setUpScene();

        $this->api('GET', '/attributes', self::USER, options: [
            'query' => ['assetId' => '9b2a7f5e-0000-4000-8000-000000000000'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListOfAnUnreadableAssetIsForbidden(): void
    {
        $this->setUpScene();
        $private = $this->createAsset(['ownerId' => self::USER]);

        $this->api('GET', '/attributes', self::OTHER, options: [
            'query' => ['assetId' => $private->getId()],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListIsOrderedByDefinitionThenPosition(): void
    {
        $this->setUpScene();
        $keywords = $this->createAttributeDefinition(['name' => 'Keywords', 'multiple' => true]);
        $this->createAttribute($this->title, 'The title');
        $this->createAttribute($keywords, 'k3', ['position' => 2]);
        $this->createAttribute($keywords, 'k1', ['position' => 0]);
        $this->createAttribute($keywords, 'k2', ['position' => 1]);

        // Definitions are sorted by position then name: "Keywords" < "Title"
        $this->assertSame(['k1', 'k2', 'k3', 'The title'], $this->listValues(self::USER));

        // Moving "Title" first
        $this->api('POST', '/attribute-definitions/sort', self::ADMIN, [$this->title->getId(), $keywords->getId()]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(['The title', 'k1', 'k2', 'k3'], $this->listValues(self::USER));
    }

    public function testListSkipsDisabledDefinitionsAndOrphanLocales(): void
    {
        $this->setUpScene();
        $disabled = $this->createAttributeDefinition(['name' => 'Disabled']);
        $disabled->setEnabled(false);
        self::getEntityManager()->persist($disabled);
        $this->createAttribute($disabled, 'hidden because disabled');
        // A localized value on a non-translatable definition is not a valid value
        $this->createAttribute($this->title, 'localized title', ['locale' => 'fr']);
        $this->createAttribute($this->title, 'The title');

        $this->assertSame(['The title'], $this->listValues(self::USER));
    }

    public function testAttributesOfANonPublicPolicyAreHiddenFromTheAssetOutput(): void
    {
        $this->setUpScene();
        $policy = $this->createSecretPolicy();
        $secret = $this->createAttributeDefinition(['name' => 'Secret', 'policy' => $policy]);
        $this->createAttribute($this->title, 'Public value');
        $this->createAttribute($secret, 'Secret value');

        $readAssetValues = function (string $userId): array {
            $response = $this->api('GET', '/assets/'.$this->asset->getId(), $userId);
            $this->assertResponseStatusCodeSame(200);

            return array_map(fn (array $a): string => $a['value'], $response->toArray()['attributes']);
        };

        $this->assertEqualsCanonicalizing(['Public value'], $readAssetValues(self::OTHER));

        // The ACL VIEW on the policy reveals it
        $this->grantUserOnObject(self::OTHER, $policy, PermissionInterface::VIEW);
        $this->assertEqualsCanonicalizing(['Public value', 'Secret value'], $readAssetValues(self::OTHER));
    }

    public function testAttributesOfANonPublicPolicyAreHiddenFromTheList(): void
    {
        $this->markTestIncomplete('BUG: GET /attributes?assetId= returns every attribute of the asset, ignoring the definition policy (AttributeCollectionProvider does not filter on AttributeDefinitionVoter::VIEW_ATTRIBUTES)');

        $this->setUpScene();
        $secret = $this->createAttributeDefinition(['name' => 'Secret', 'policy' => $this->createSecretPolicy()]);
        $this->createAttribute($this->title, 'Public value');
        $this->createAttribute($secret, 'Secret value');

        $this->assertSame(['Public value'], $this->listValues(self::OTHER));
    }

    public function testGetItem(): void
    {
        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title', ['position' => 3]);

        $response = $this->api('GET', '/attributes/'.$attribute->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'attribute',
            'id' => $attribute->getId(),
            'value' => 'The title',
            'locale' => '_',
            'position' => 3,
            'origin' => 'human',
            'status' => 'valid',
            'invalid' => false,
            'definition' => [
                '@id' => '/attribute-definitions/'.$this->title->getId(),
                'name' => 'Title',
                'slug' => 'title',
            ],
            'asset' => [
                '@id' => '/assets/'.$this->asset->getId(),
            ],
        ]);
    }

    public function testGetItemOfAnUnknownAttributeIsNotFound(): void
    {
        $this->setUpScene();

        $this->api('GET', '/attributes/9b2a7f5e-0000-4000-8000-000000000000', self::USER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetItemIsDeniedToAnonymous(): void
    {
        $this->markTestIncomplete('BUG: GET /attributes/{id} has no security expression (src/Entity/Core/Attribute.php:35 "new Get()"): anyone, even anonymous, reads any attribute value');

        $this->setUpScene();
        $private = $this->createAsset(['ownerId' => self::USER]);
        $attribute = $this->createAttribute($this->title, 'The title');
        $attribute->setAsset($private);
        self::getEntityManager()->flush();

        $this->api('GET', '/attributes/'.$attribute->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetItemOfANonPublicPolicyIsDenied(): void
    {
        $this->markTestIncomplete('BUG: GET /attributes/{id} has no security expression (src/Entity/Core/Attribute.php:35 "new Get()"): the policy visibility (AttributeVoter READ) is never checked');

        $this->setUpScene();
        $secret = $this->createAttributeDefinition(['name' => 'Secret', 'policy' => $this->createSecretPolicy()]);
        $attribute = $this->createAttribute($secret, 'Secret value');

        $this->api('GET', '/attributes/'.$attribute->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreate(): void
    {
        $this->setUpScene();

        $data = $this->postAttribute(self::USER, $this->title, '  Hello  ', [
            'origin' => 'human',
            'status' => 'review_pending',
            'confidence' => 0.5,
            'originVendor' => 'acme',
            'originVendorContext' => 'v1',
            'position' => 2,
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Hello', $data['value'], 'the value is trimmed');
        $this->assertSame('human', $data['origin']);
        $this->assertSame('review_pending', $data['status']);
        $this->assertEquals(0.5, $data['confidence']);
        $this->assertSame('acme', $data['originVendor']);
        $this->assertSame('v1', $data['originVendorContext']);
        $this->assertSame(2, $data['position']);

        $this->assertSame(['Hello'], $this->listValues(self::USER));
    }

    public function testCreateDefaultsToAMachineOriginAndAValidStatus(): void
    {
        $this->setUpScene();

        $data = $this->postAttribute(self::USER, $this->title, 'Hello');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('machine', $data['origin']);
        $this->assertSame('valid', $data['status']);
        $this->assertEquals(1, $data['confidence']);
        $this->assertSame(0, $data['position']);
    }

    public function testCreateNormalizesTheValueByType(): void
    {
        $this->setUpScene();
        $number = $this->createAttributeDefinition(['name' => 'Count', 'type' => NumberAttributeType::NAME]);

        $data = $this->postAttribute(self::USER, $number, '42');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(42, $data['value']);
    }

    /**
     * @dataProvider getInvalidMetaCases
     */
    public function testCreateRejectsAnInvalidOriginOrStatus(array $extra): void
    {
        $this->setUpScene();

        $this->postAttribute(self::USER, $this->title, 'Hello', $extra);
        $this->assertResponseStatusCodeSame(400);
    }

    public static function getInvalidMetaCases(): array
    {
        return [
            'origin' => [['origin' => 'robot']],
            'status' => [['status' => 'maybe']],
        ];
    }

    public function testCreateRequiresADefinition(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attributes', self::USER, [
            'asset' => '/assets/'.$this->asset->getId(),
            'value' => 'Hello',
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Missing Attribute definition']);
    }

    public function testCreateWithAnEmptyValueIsABadRequest(): void
    {
        $this->markTestIncomplete('BUG: POST /attributes with an empty value answers 403: AttributeInputTransformer::transform() returns null, then securityPostDenormalize is_granted("CREATE", null) is denied (src/Api/InputTransformer/AttributeInputTransformer.php:46)');

        $this->setUpScene();

        $this->postAttribute(self::USER, $this->title, '   ');
        $this->assertContains(static::getClient()->getResponse()->getStatusCode(), [400, 422]);
        $this->assertSame([], $this->listValues(self::USER));
    }

    public function testCreateIsDeniedToAnonymous(): void
    {
        $this->setUpScene();

        $this->postAttribute(null, $this->title, 'Hello');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testASingleValueDefinitionAcceptsOnlyOneValue(): void
    {
        $this->setUpScene();

        $this->postAttribute(self::USER, $this->title, 'First');
        $this->assertResponseStatusCodeSame(201);

        $this->postAttribute(self::USER, $this->title, 'Second');
        $this->assertResponseStatusCodeSame(422);

        $this->assertSame(['First'], $this->listValues(self::USER));
    }

    public function testAMultipleDefinitionAcceptsSeveralPositionedValues(): void
    {
        $this->setUpScene();
        $keywords = $this->createAttributeDefinition(['name' => 'Keywords', 'multiple' => true]);

        $this->postAttribute(self::USER, $keywords, 'second', ['position' => 1]);
        $this->assertResponseStatusCodeSame(201);
        $this->postAttribute(self::USER, $keywords, 'first', ['position' => 0]);
        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(['first', 'second'], $this->listValues(self::USER));
    }

    public function testATranslatableDefinitionAcceptsOneValuePerLocale(): void
    {
        $this->setUpScene();
        $description = $this->createAttributeDefinition(['name' => 'Description', 'translatable' => true]);

        $fr = $this->postAttribute(self::USER, $description, 'Bonjour', ['locale' => 'fr-FR']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('fr_FR', $fr['locale'], 'the locale is normalized');

        $en = $this->postAttribute(self::USER, $description, 'Hello', ['locale' => 'en']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('en', $en['locale']);

        $this->postAttribute(self::USER, $description, 'Salut', ['locale' => 'fr_FR']);
        $this->assertResponseStatusCodeSame(422);

        $this->assertEqualsCanonicalizing(['Bonjour', 'Hello'], $this->listValues(self::USER));
    }

    public function testALocaleOnANonTranslatableDefinitionDoesNotHideTheValue(): void
    {
        $this->markTestIncomplete('BUG: POST /attributes with a locale on a non-translatable definition answers 201 (locale "_") but stores the locale, so AbstractBaseAttribute::isValidValue() hides the value from every read afterwards: AttributeAssigner sets the locale whatever the definition (src/Attribute/AttributeAssigner.php:74-75)');

        $this->setUpScene();

        $data = $this->postAttribute(self::USER, $this->title, 'Hello', ['locale' => 'fr']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('_', $data['locale']);

        $this->assertSame(['Hello'], $this->listValues(self::USER));
    }

    public function testAnInvalidValueIsRejected(): void
    {
        $this->setUpScene();
        $ip = $this->createAttributeDefinition(['name' => 'IP', 'type' => IpAttributeType::NAME]);

        $this->postAttribute(self::USER, $ip, 'not-an-ip');
        $this->assertResponseStatusCodeSame(422);

        $data = $this->postAttribute(self::USER, $ip, '192.168.0.1');
        $this->assertResponseStatusCodeSame(201);
        $this->assertFalse($data['invalid']);
    }

    public function testAnInvalidValueIsKeptAndFlaggedWhenTheDefinitionAllowsIt(): void
    {
        $this->setUpScene();
        $ip = $this->createAttributeDefinition([
            'name' => 'IP',
            'type' => IpAttributeType::NAME,
            'allow_invalid' => true,
        ]);

        $data = $this->postAttribute(self::USER, $ip, 'not-an-ip');
        $this->assertResponseStatusCodeSame(201);
        $this->assertTrue($data['invalid']);
        $this->assertSame('not-an-ip', $data['value']);
    }

    public function testWritesRequireTheRightToEditTheAssetAttributes(): void
    {
        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title');

        // OTHER can read the (public) asset but not edit it
        $this->postAttribute(self::OTHER, $this->createAttributeDefinition(['name' => 'Other']), 'x');
        $this->assertResponseStatusCodeSame(403);
        $this->api('PUT', '/attributes/'.$attribute->getId(), self::OTHER, ['value' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', '/attributes/'.$attribute->getId(), self::OTHER, ['value' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/attributes/'.$attribute->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(['The title'], $this->listValues(self::USER));
    }

    public function testANonEditablePolicyRequiresTheEditPermissionOnThePolicy(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy([
            'name' => 'Locked',
            'public' => true,
            'editable' => false,
        ]);
        $locked = $this->createAttributeDefinition(['name' => 'Locked', 'policy' => $policy]);
        $lockedMulti = $this->createAttributeDefinition(['name' => 'Locked multi', 'policy' => $policy, 'multiple' => true]);
        $attribute = $this->createAttribute($locked, 'Read only');

        $this->postAttribute(self::USER, $lockedMulti, 'x');
        $this->assertResponseStatusCodeSame(403);
        $this->api('PUT', '/attributes/'.$attribute->getId(), self::USER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/attributes/'.$attribute->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        // An admin bypasses the policy
        $this->postAttribute(self::ADMIN, $lockedMulti, 'by admin');
        $this->assertResponseStatusCodeSame(201);

        $this->grantUserOnObject(self::USER, $policy, PermissionInterface::EDIT);
        $this->postAttribute(self::USER, $lockedMulti, 'granted');
        $this->assertResponseStatusCodeSame(201);
        $this->api('PUT', '/attributes/'.$attribute->getId(), self::USER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Changed']);
    }

    public function testANonEditableDefinitionCannotBeWritten(): void
    {
        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title');
        // AttributeDefinition is tracked with DEFERRED_EXPLICIT: persist() is required
        $this->title->setEditable(false);
        self::getEntityManager()->persist($this->title);
        self::getEntityManager()->flush();

        $this->api('PUT', '/attributes/'.$attribute->getId(), self::USER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/attributes/'.$attribute->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdate(): void
    {
        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title');

        $this->api('PUT', '/attributes/'.$attribute->getId(), self::USER, [
            'value' => 'New title',
            'origin' => 'machine',
            'originVendor' => 'ai',
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'id' => $attribute->getId(),
            'value' => 'New title',
            'origin' => 'machine',
            'originVendor' => 'ai',
        ]);

        $this->api('PATCH', '/attributes/'.$attribute->getId(), self::USER, ['value' => 'Patched title']);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Patched title']);

        $this->assertSame(['Patched title'], $this->listValues(self::USER));
    }

    public function testUpdateWithAnEmptyValueRemovesTheAttribute(): void
    {
        $this->markTestIncomplete('BUG: PUT /attributes/{id} with an empty value answers 200 with a "null" body but keeps the attribute: AttributeInputTransformer schedules the removal and returns null (src/Api/InputTransformer/AttributeInputTransformer.php:41-45), and nothing flushes it');

        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title');

        $this->api('PUT', '/attributes/'.$attribute->getId(), self::USER, ['value' => '']);
        $this->assertResponseIsSuccessful();

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Attribute::class, $attribute->getId()));
        $this->assertSame([], $this->listValues(self::USER));
    }

    public function testDelete(): void
    {
        $this->setUpScene();
        $attribute = $this->createAttribute($this->title, 'The title');

        $this->api('DELETE', '/attributes/'.$attribute->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Attribute::class, $attribute->getId()));
        $this->assertSame([], $this->listValues(self::USER));
    }

    public function testADefinitionOfAnotherWorkspaceIsRejected(): void
    {
        $this->markTestIncomplete('BUG: POST /attributes accepts a definition of another workspace than the asset one: AttributeInputTransformer calls getAttributeDefinitionFromInput() without workspace (src/Api/InputTransformer/AttributeInputTransformer.php:37), so the "Workspace inconsistency" check of AttributeInputTrait is skipped');

        $this->setUpScene();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $foreign = $this->createAttributeDefinition([
            'name' => 'Foreign',
            'workspace' => $otherWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $otherWorkspace]),
        ]);

        $this->postAttribute(self::USER, $foreign, 'Cross workspace');
        $this->assertResponseStatusCodeSame(400);
    }
}
