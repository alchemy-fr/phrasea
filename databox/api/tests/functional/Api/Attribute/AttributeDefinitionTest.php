<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * /attribute-definitions: the attribute schema of a workspace.
 *
 * Reading the full definition and writing it require the EDIT permission on the
 * workspace; the list is filtered by workspace membership and policy visibility.
 */
final class AttributeDefinitionTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member.
     */
    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
    }

    private function definitionPayload(array $data = []): array
    {
        return array_merge([
            'workspace' => '/workspaces/'.$this->workspace->getId(),
            'policy' => '/attribute-policies/'.$this->getOrCreateDefaultAttributePolicy()->getId(),
            'name' => 'Title',
            'type' => TextAttributeType::NAME,
        ], $data);
    }

    private function listNames(?string $userId, array $query = [], array $headers = []): array
    {
        $response = $this->api('GET', '/attribute-definitions', $userId, options: [
            'query' => $query,
            'headers' => $headers,
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Every workspace comes with a "Name" definition (see WorkspaceCreator), ignored here
        return array_values(array_filter(
            array_column($response->toArray()['member'], 'name'),
            fn (string $name): bool => 'Name' !== $name,
        ));
    }

    private function persistDefinition(AttributeDefinition $definition): void
    {
        // AttributeDefinition is tracked with DEFERRED_EXPLICIT: persist() is required
        $em = self::getEntityManager();
        $em->persist($definition);
        $em->flush();
    }

    public function testListIsFilteredByWorkspaceMembershipAndPolicyVisibility(): void
    {
        $this->setUpScene();
        $this->createAttributeDefinition(['name' => 'Title']);
        $secretPolicy = $this->createAttributePolicy(['name' => 'Secret', 'public' => false]);
        $this->createAttributeDefinition(['name' => 'Secret', 'policy' => $secretPolicy]);

        $foreignWorkspace = $this->createOtherWorkspace();
        $this->createAttributeDefinition([
            'name' => 'Foreign',
            'workspace' => $foreignWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $foreignWorkspace]),
        ]);

        // Members only list the definitions of public policies; a policy has no owner (getAclOwnerId() is empty)
        // so even the workspace owner needs a VIEW ACE on a non-public policy
        $this->assertEqualsCanonicalizing(['Title'], $this->listNames(self::OTHER));
        $this->assertEqualsCanonicalizing(['Title'], $this->listNames(self::USER));
        $this->assertEqualsCanonicalizing(['Title', 'Secret', 'Foreign'], $this->listNames(self::ADMIN));

        $this->grantUserOnObject(self::OTHER, $secretPolicy, PermissionInterface::VIEW);
        $this->assertEqualsCanonicalizing(['Title', 'Secret'], $this->listNames(self::OTHER));
    }

    public function testAnonymousListsOnlyPublicWorkspacesAndPolicies(): void
    {
        $this->setUpScene();
        $this->createAttributeDefinition(['name' => 'Private workspace']);

        $publicWorkspace = $this->createOtherWorkspace(['public' => true]);
        $this->createAttributeDefinition([
            'name' => 'Public workspace',
            'workspace' => $publicWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Open', 'workspace' => $publicWorkspace]),
        ]);
        $this->createAttributeDefinition([
            'name' => 'Public workspace, secret policy',
            'workspace' => $publicWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Secret', 'workspace' => $publicWorkspace, 'public' => false]),
        ]);

        $this->assertSame(['Public workspace'], $this->listNames(null));
    }

    public function testListFilters(): void
    {
        $this->setUpScene();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $this->createAttributeDefinition(['name' => 'Title']);
        $this->createAttributeDefinition(['name' => 'Subtitle', 'searchable' => false]);
        $count = $this->createAttributeDefinition(['name' => 'Count', 'type' => NumberAttributeType::NAME]);
        $count->setTarget(AssetTypeEnum::Story);
        $this->persistDefinition($count);
        $both = $this->createAttributeDefinition(['name' => 'Both']);
        $both->setTarget(AssetTypeEnum::Both);
        $this->persistDefinition($both);
        $this->createAttributeDefinition([
            'name' => 'Elsewhere',
            'workspace' => $otherWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Default', 'workspace' => $otherWorkspace]),
        ]);

        $this->assertEqualsCanonicalizing(['Title', 'Subtitle', 'Count', 'Both'], $this->listNames(self::USER, [
            'workspaceId' => $this->workspace->getId(),
        ]));
        $this->assertEqualsCanonicalizing(['Elsewhere'], $this->listNames(self::USER, [
            'workspaceIds' => [$otherWorkspace->getId()],
        ]));
        $this->assertEqualsCanonicalizing(['Count'], $this->listNames(self::USER, [
            'type' => NumberAttributeType::NAME,
        ]));
        $this->assertEqualsCanonicalizing(['Subtitle'], $this->listNames(self::USER, [
            'searchable' => 'false',
        ]));
        $this->assertEqualsCanonicalizing(['Title', 'Subtitle'], $this->listNames(self::USER, [
            'name' => 'title',
        ]));
        // target is a bitmask: "Both" matches assets and stories
        $this->assertEqualsCanonicalizing(['Count', 'Both'], $this->listNames(self::USER, [
            'target' => AssetTypeEnum::Story->value,
        ]));
        $this->assertEqualsCanonicalizing(['Title', 'Subtitle', 'Both', 'Elsewhere'], $this->listNames(self::USER, [
            'target' => AssetTypeEnum::Asset->value,
        ]));
    }

    public function testListIsOrderedByWorkspaceThenPositionThenName(): void
    {
        $this->setUpScene();
        $this->createAttributeDefinition(['name' => 'Bravo']);
        $this->createAttributeDefinition(['name' => 'Alpha']);
        $charlie = $this->createAttributeDefinition(['name' => 'Charlie']);
        $charlie->setPosition(-1);
        $this->persistDefinition($charlie);

        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->listNames(self::USER, [
            'workspaceId' => $this->workspace->getId(),
        ]));
    }

    public function testReadingADefinitionRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $iri = '/attribute-definitions/'.$definition->getId();

        $this->api('GET', $iri);
        $this->assertResponseStatusCodeSame(401);

        $this->api('GET', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->api('GET', $iri, self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'attribute-definition',
            'id' => $definition->getId(),
            'name' => 'Title',
            'slug' => 'title',
            'searchSlug' => 'title_text_s',
            'type' => TextAttributeType::NAME,
            'canEdit' => true,
        ]);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->api('GET', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testCreate(): void
    {
        $this->setUpScene();

        $response = $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'name' => 'Date de prise',
            'multiple' => true,
            'translatable' => true,
            'searchable' => false,
            'facetEnabled' => true,
            'sortable' => true,
            'allowInvalid' => true,
            'fallback' => ['en' => '{{ file.filename }}'],
            'readFromMetadata' => [' IPTC:Credit ', ''],
            'target' => AssetTypeEnum::Both->value,
            'position' => 3,
        ]));
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Date de prise', $data['name']);
        $this->assertSame('datedeprise', $data['slug'], 'the slug is generated from the name');
        $this->assertSame('datedeprise_text_m', $data['searchSlug']);
        $this->assertTrue($data['multiple']);
        $this->assertTrue($data['translatable']);
        $this->assertFalse($data['searchable']);
        $this->assertTrue($data['facetEnabled']);
        $this->assertTrue($data['sortable']);
        $this->assertTrue($data['allowInvalid']);
        $this->assertSame(['en' => '{{ file.filename }}'], $data['fallback']);
        $this->assertSame(['IPTC:Credit'], $data['readFromMetadata'], 'the metadata list is trimmed');
        $this->assertSame(AssetTypeEnum::Both->value, $data['target']);
        $this->assertSame(3, $data['position']);
        $this->assertTrue($data['enabled']);
        $this->assertSame('/workspaces/'.$this->workspace->getId(), $data['workspace']['@id'] ?? $data['workspace']);
    }

    public function testCreateRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attribute-definitions', null, $this->definitionPayload());
        $this->assertResponseStatusCodeSame(401);

        $this->api('POST', '/attribute-definitions', self::OTHER, $this->definitionPayload());
        $this->assertResponseStatusCodeSame(403);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->api('POST', '/attribute-definitions', self::OTHER, $this->definitionPayload());
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateWithoutWorkspaceIsABadRequest(): void
    {
        $this->setUpScene();

        $payload = $this->definitionPayload();
        unset($payload['workspace']);
        $this->api('POST', '/attribute-definitions', self::USER, $payload);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['description' => 'Missing workspace']);
    }

    public function testThePolicyMustBelongToTheSameWorkspace(): void
    {
        $this->setUpScene();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $foreignPolicy = $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $otherWorkspace]);

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'policy' => '/attribute-policies/'.$foreignPolicy->getId(),
        ]));
        $this->assertResponseStatusCodeSame(422);

        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $this->api('PATCH', '/attribute-definitions/'.$definition->getId(), self::USER, [
            'policy' => '/attribute-policies/'.$foreignPolicy->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testTheNameIsRequiredAndUniqueInTheWorkspace(): void
    {
        $this->setUpScene();
        $this->createAttributeDefinition(['name' => 'Title']);

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload(['name' => '']));
        $this->assertResponseStatusCodeSame(422);

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload(['name' => 'Title']));
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'name']]]);

        // The same name is allowed in another workspace
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            'policy' => '/attribute-policies/'.$this->createAttributePolicy(['name' => 'P', 'workspace' => $otherWorkspace])->getId(),
        ]));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAnEntityDefinitionRequiresAnEntityList(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'type' => EntityAttributeType::NAME,
        ]));
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'entityList', 'message' => 'Missing entity list']]]);

        $list = $this->createEntityList(['name' => 'Colors']);
        $response = $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'type' => EntityAttributeType::NAME,
            'entityList' => '/entity-lists/'.$list->getId(),
        ]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('/entity-lists/'.$list->getId(), $response->toArray()['entityList']['@id'] ?? $response->toArray()['entityList']);
    }

    public function testTheEntityListMustBelongToTheWorkspace(): void
    {
        $this->setUpScene();
        $foreignWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $foreignList = $this->createEntityList(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'type' => EntityAttributeType::NAME,
            'entityList' => '/entity-lists/'.$foreignList->getId(),
        ]));
        $this->assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownTypeIsRejected(): void
    {
        $this->markTestIncomplete('BUG: an unknown attribute type answers 500: AttributeDefinition::$type has no Choice constraint (src/Entity/Core/AttributeDefinition.php:202), so the definition is stored and AttributeTypeRegistry::getStrictType() throws when the output is built');

        $this->setUpScene();

        $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'type' => 'not-a-type',
        ]));
        $this->assertContains(static::getClient()->getResponse()->getStatusCode(), [400, 422]);
    }

    public function testPostingAKnownKeyUpdatesTheExistingDefinition(): void
    {
        $this->setUpScene();

        $first = $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'key' => 'title-key',
            'name' => 'Title',
        ]))->toArray();
        $this->assertResponseStatusCodeSame(201);

        $second = $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'key' => 'title-key',
            'name' => 'Renamed title',
        ]))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('Renamed title', $second['name']);
        $this->assertSame(['Renamed title'], $this->listNames(self::USER));
    }

    public function testTranslatedName(): void
    {
        $this->setUpScene();

        $response = $this->api('POST', '/attribute-definitions', self::USER, $this->definitionPayload([
            'name' => 'Title',
            'translations' => [
                'name' => [
                    'fr' => 'Titre',
                    'de' => 'Titel',
                ],
            ],
        ]));
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(['name' => ['fr' => 'Titre', 'de' => 'Titel']], $data['translations']);
        $iri = $data['@id'];

        $displayName = function (array $headers) use ($iri): string {
            return $this->api('GET', $iri, self::USER, options: ['headers' => $headers])->toArray()['displayName'];
        };

        $this->assertSame('Title', $displayName([]));
        $this->assertSame('Titre', $displayName(['Accept-Language' => 'fr-FR,fr;q=0.9']));
        $this->assertSame('Titel', $displayName(['Accept-Language' => 'de']));
        $this->assertSame('Titel', $displayName(['Accept-Language' => 'fr', 'X-Data-Locale' => 'de']));
        $this->assertSame('Title', $displayName(['Accept-Language' => 'es']));
    }

    public function testUpdate(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $iri = '/attribute-definitions/'.$definition->getId();

        $this->api('PATCH', $iri, self::USER, [
            'name' => 'Main title',
            'searchable' => false,
            'multiple' => true,
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'name' => 'Main title',
            'slug' => 'title',
            'searchable' => false,
            'multiple' => true,
        ]);

        $this->api('PATCH', $iri, self::USER, ['facetEnabled' => true]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'name' => 'Main title',
            'facetEnabled' => true,
        ]);
    }

    public function testTheWorkspaceOfADefinitionCannotBeChanged(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);

        $this->api('PATCH', '/attribute-definitions/'.$definition->getId(), self::USER, [
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
        ]);
        $this->assertResponseIsSuccessful();

        self::getEntityManager()->clear();
        $this->assertSame(
            $this->workspace->getId(),
            self::getEntityManager()->find(AttributeDefinition::class, $definition->getId())->getWorkspaceId(),
        );
    }

    public function testAllowInvalidCanBeDisabled(): void
    {
        $this->markTestIncomplete('BUG: "allowInvalid" can be enabled but never disabled through the API: AttributeDefinitionInputTransformer only applies a truthy value (src/Api/InputTransformer/AttributeDefinitionInputTransformer.php:74)');

        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title', 'allow_invalid' => true]);

        $this->api('PATCH', '/attribute-definitions/'.$definition->getId(), self::USER, [
            'allowInvalid' => false,
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['allowInvalid' => false]);
    }

    public function testWritesRequireTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $iri = '/attribute-definitions/'.$definition->getId();

        $this->api('PATCH', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri);
        $this->assertResponseStatusCodeSame(401);
    }

    #[DataProvider('getTypeChangeCases')]
    public function testTypeChange(string $from, string $to, int $expectedCode): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Field', 'type' => $from]);

        $this->api('PATCH', '/attribute-definitions/'.$definition->getId(), self::USER, [
            'type' => $to,
        ]);
        $this->assertResponseStatusCodeSame($expectedCode);

        self::getEntityManager()->clear();
        $this->assertSame(
            200 === $expectedCode ? $to : $from,
            self::getEntityManager()->find(AttributeDefinition::class, $definition->getId())->getType(),
        );
    }

    public static function getTypeChangeCases(): array
    {
        return [
            'text to keyword' => [TextAttributeType::NAME, KeywordAttributeType::NAME, 200],
            'number to text' => [NumberAttributeType::NAME, TextAttributeType::NAME, 200],
            'text to number' => [TextAttributeType::NAME, NumberAttributeType::NAME, 400],
        ];
    }

    public function testDeleteRemovesTheAttributesOfTheDefinition(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Title']);
        $kept = $this->createAttributeDefinition(['name' => 'Kept']);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $definition, 'value' => 'Deleted value'],
                ['definition' => $kept, 'value' => 'Kept value'],
            ],
        ]);
        $assetId = $asset->getId();
        // Forget the in-memory definition, whose attribute collection was initialized empty
        self::getEntityManager()->clear();

        $this->api('DELETE', '/attribute-definitions/'.$definition->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(AttributeDefinition::class, $definition->getId()));
        $values = array_map(
            fn (Attribute $attribute): string => $attribute->getValue(),
            $em->getRepository(Attribute::class)->findBy(['asset' => $assetId]),
        );
        $this->assertSame(['Kept value'], $values);
    }

    public function testSort(): void
    {
        $this->setUpScene();
        $a = $this->createAttributeDefinition(['name' => 'A']);
        $b = $this->createAttributeDefinition(['name' => 'B']);
        $c = $this->createAttributeDefinition(['name' => 'C']);

        $this->api('POST', '/attribute-definitions/sort', self::USER, [$c->getId(), $a->getId(), $b->getId()]);
        $this->assertResponseIsSuccessful();

        $this->assertSame(['C', 'A', 'B'], $this->listNames(self::USER, [
            'workspaceId' => $this->workspace->getId(),
        ]));
    }

    public function testSortRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $a = $this->createAttributeDefinition(['name' => 'A']);
        $b = $this->createAttributeDefinition(['name' => 'B']);

        $this->api('POST', '/attribute-definitions/sort', null, [$b->getId(), $a->getId()]);
        $this->assertResponseStatusCodeSame(401);

        $this->api('POST', '/attribute-definitions/sort', self::OTHER, [$b->getId(), $a->getId()]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(['A', 'B'], $this->listNames(self::USER, [
            'workspaceId' => $this->workspace->getId(),
        ]));
    }

    public function testSortOnlyMovesDefinitionsOfTheFirstItemWorkspace(): void
    {
        $this->setUpScene();
        $a = $this->createAttributeDefinition(['name' => 'A']);
        $b = $this->createAttributeDefinition(['name' => 'B']);

        $foreignWorkspace = $this->createOtherWorkspace();
        $foreign = $this->createAttributeDefinition([
            'name' => 'Foreign',
            'workspace' => $foreignWorkspace,
            'policy' => $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $foreignWorkspace]),
        ]);
        $foreign->setPosition(42);
        $this->persistDefinition($foreign);

        $this->api('POST', '/attribute-definitions/sort', self::USER, [$b->getId(), $foreign->getId(), $a->getId()]);
        $this->assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame(0, $em->find(AttributeDefinition::class, $b->getId())->getPosition());
        $this->assertSame(2, $em->find(AttributeDefinition::class, $a->getId())->getPosition());
        $this->assertSame(42, $em->find(AttributeDefinition::class, $foreign->getId())->getPosition(), 'a definition of another workspace is left untouched');
    }

    public function testSortEdgeCases(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attribute-definitions/sort', self::USER, []);
        $this->assertResponseIsSuccessful();

        $this->api('POST', '/attribute-definitions/sort', self::USER, ['9b2a7f5e-0000-4000-8000-000000000000']);
        $this->assertResponseStatusCodeSame(404);
    }
}
