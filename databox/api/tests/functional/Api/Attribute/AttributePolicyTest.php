<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /attribute-policies: groups of attribute definitions sharing visibility (public) and
 * edition (editable) rules. Managed by the workspace editors.
 */
final class AttributePolicyTest extends AbstractDataboxTestCase
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

    private function policyPayload(array $data = []): array
    {
        return array_merge([
            'workspace' => '/workspaces/'.$this->workspace->getId(),
            'name' => 'Restricted',
            'public' => false,
            'editable' => true,
        ], $data);
    }

    private function listNames(string $userId, array $query = []): array
    {
        $response = $this->api('GET', '/attribute-policies', $userId, options: ['query' => $query]);
        $this->assertResponseStatusCodeSame(200);

        // Every workspace comes with a "Public" policy (see WorkspaceCreator), ignored here
        return array_values(array_filter(
            array_column($response->toArray()['hydra:member'], 'name'),
            fn (string $name): bool => 'Public' !== $name,
        ));
    }

    public function testAnonymousIsDenied(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted']);

        $this->api('GET', '/attribute-policies');
        $this->assertResponseStatusCodeSame(401);
        $this->api('GET', '/attribute-policies/'.$policy->getId());
        $this->assertResponseStatusCodeSame(401);
        $this->api('POST', '/attribute-policies', null, $this->policyPayload());
        $this->assertResponseStatusCodeSame(401);
        $this->api('PUT', '/attribute-policies/'.$policy->getId(), null, ['name' => 'Anonymous']);
        $this->assertResponseStatusCodeSame(401);
        $this->api('DELETE', '/attribute-policies/'.$policy->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListOnlyShowsThePoliciesOfEditableWorkspaces(): void
    {
        $this->setUpScene();
        $this->createAttributePolicy(['name' => 'Restricted']);
        $foreignWorkspace = $this->createOtherWorkspace();
        $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);

        $this->assertSame(['Restricted'], $this->listNames(self::USER));
        $this->assertSame([], $this->listNames(self::OTHER), 'a mere member does not list the policies');
        $this->assertEqualsCanonicalizing(['Restricted', 'Foreign'], $this->listNames(self::ADMIN));

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->assertSame(['Restricted'], $this->listNames(self::OTHER));
    }

    public function testListFilterByWorkspace(): void
    {
        $this->setUpScene();
        $this->createAttributePolicy(['name' => 'Restricted']);
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $this->createAttributePolicy(['name' => 'Elsewhere', 'workspace' => $otherWorkspace]);

        $this->assertEqualsCanonicalizing(['Restricted', 'Elsewhere'], $this->listNames(self::USER));
        $this->assertSame(['Elsewhere'], $this->listNames(self::USER, ['workspaceId' => $otherWorkspace->getId()]));
        $this->assertSame(['Restricted'], $this->listNames(self::ADMIN, ['workspaceId' => $this->workspace->getId()]));
    }

    public function testGetItemRequiresTheWorkspaceReadPermission(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted', 'public' => false, 'editable' => false]);
        $iri = '/attribute-policies/'.$policy->getId();

        foreach ([self::USER, self::OTHER] as $userId) {
            $this->api('GET', $iri, $userId);
            $this->assertResponseStatusCodeSame(200);
            $this->assertJsonContains([
                '@type' => 'attribute-policy',
                'id' => $policy->getId(),
                'name' => 'Restricted',
                'public' => false,
                'editable' => false,
            ]);
        }

        $foreignWorkspace = $this->createOtherWorkspace();
        $foreign = $this->createAttributePolicy(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);
        $this->api('GET', '/attribute-policies/'.$foreign->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);

        $this->api('GET', '/attribute-policies/9b2a7f5e-0000-4000-8000-000000000000', self::USER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreate(): void
    {
        $this->setUpScene();

        $response = $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload());
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'attribute-policy',
            'name' => 'Restricted',
            'public' => false,
            'editable' => true,
        ]);

        $policy = self::getEntityManager()->find(AttributePolicy::class, $response->toArray()['id']);
        $this->assertSame($this->workspace->getId(), $policy->getWorkspaceId());
    }

    public function testCreateRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attribute-policies', self::OTHER, $this->policyPayload());
        $this->assertResponseStatusCodeSame(403);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->api('POST', '/attribute-policies', self::OTHER, $this->policyPayload());
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateValidation(): void
    {
        $this->setUpScene();
        $this->createAttributePolicy(['name' => 'Existing']);

        $payload = $this->policyPayload();
        unset($payload['workspace']);
        $this->api('POST', '/attribute-policies', self::USER, $payload);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Missing workspace']);

        $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload(['name' => null]));
        $this->assertResponseStatusCodeSame(422);

        // "public" and "editable" are mandatory
        $payload = $this->policyPayload();
        unset($payload['public']);
        $this->api('POST', '/attribute-policies', self::USER, $payload);
        $this->assertResponseStatusCodeSame(422);

        // and typed as booleans
        $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload(['editable' => null]));
        $this->assertResponseStatusCodeSame(400);

        $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload(['name' => 'Existing']));
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [[
            'propertyPath' => 'name',
            'message' => 'The attribute policy name must be unique in the workspace.',
        ]]]);
    }

    public function testPostingAKnownKeyUpdatesTheExistingPolicy(): void
    {
        $this->setUpScene();

        $first = $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload(['key' => 'restricted']))->toArray();
        $this->assertResponseStatusCodeSame(201);

        $second = $this->api('POST', '/attribute-policies', self::USER, $this->policyPayload([
            'key' => 'restricted',
            'name' => 'Renamed',
            'public' => true,
        ]))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('Renamed', $second['name']);
        $this->assertTrue($second['public']);
        $this->assertSame(['Renamed'], $this->listNames(self::USER));
    }

    public function testUpdate(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted', 'public' => true, 'editable' => true]);
        $iri = '/attribute-policies/'.$policy->getId();

        $this->api('PUT', $iri, self::USER, ['name' => 'Locked', 'editable' => false]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Locked', 'public' => true, 'editable' => false]);

        $this->api('PATCH', $iri, self::USER, ['public' => false]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Locked', 'public' => false, 'editable' => false]);
    }

    public function testTheWorkspaceOfAPolicyCannotBeChanged(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted']);
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);

        $this->api('PUT', '/attribute-policies/'.$policy->getId(), self::USER, [
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
        ]);
        $this->assertResponseIsSuccessful();

        self::getEntityManager()->clear();
        $this->assertSame($this->workspace->getId(), self::getEntityManager()->find(AttributePolicy::class, $policy->getId())->getWorkspaceId());
    }

    public function testWritesRequireTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted']);
        $iri = '/attribute-policies/'.$policy->getId();

        $this->api('PUT', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', $iri, self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->api('PATCH', $iri, self::OTHER, ['name' => 'Granted']);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteRemovesTheDefinitionsAndTheirAttributes(): void
    {
        $this->setUpScene();
        $policy = $this->createAttributePolicy(['name' => 'Restricted']);
        $definition = $this->createAttributeDefinition(['name' => 'Restricted field', 'policy' => $policy]);
        $kept = $this->createAttributeDefinition(['name' => 'Kept']);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $definition, 'value' => 'Deleted value'],
                ['definition' => $kept, 'value' => 'Kept value'],
            ],
        ]);
        $assetId = $asset->getId();
        self::getEntityManager()->clear();

        $this->api('DELETE', '/attribute-policies/'.$policy->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(AttributePolicy::class, $policy->getId()));
        $this->assertNull($em->find(AttributeDefinition::class, $definition->getId()));
        $this->assertNotNull($em->find(AttributeDefinition::class, $kept->getId()));
        $this->assertSame(['Kept value'], array_map(
            fn (Attribute $attribute): string => $attribute->getValue(),
            $em->getRepository(Attribute::class)->findBy(['asset' => $assetId]),
        ));
    }
}
