<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /attribute-filter-rules: AQL conditions restricting, per user or group, the assets
 * on which a workspace applies. Complements Api\AttributeFilterRuleTest.
 */
final class AttributeFilterRuleApiTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;
    private string $condition;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member.
     */
    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->condition = sprintf('@tag = "%s"', $this->findOrCreateTagByName('foo', $this->workspace)->getId());
    }

    private function createRule(?Workspace $workspace = null, array $userIds = [self::OTHER], array $groupIds = []): AttributeFilterRule
    {
        $rule = new AttributeFilterRule();
        $rule->setWorkspace($workspace ?? $this->workspace);
        $rule->setTargets($userIds, $groupIds);
        $rule->setCondition($this->condition);

        $em = self::getEntityManager();
        $em->persist($rule);
        $em->flush();

        return $rule;
    }

    private function postRule(?string $userId, array $data): array
    {
        return $this->api('POST', '/attribute-filter-rules', $userId, array_merge([
            'workspaceId' => $this->workspace->getId(),
            'userIds' => [self::OTHER],
            'condition' => $this->condition,
        ], $data))->toArray(false);
    }

    private function listIds(string $userId, array $query = []): array
    {
        $response = $this->api('GET', '/attribute-filter-rules', $userId, options: ['query' => $query]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['hydra:member'], 'id');
    }

    public function testAnonymousIsDenied(): void
    {
        $this->setUpScene();
        $rule = $this->createRule();

        $this->api('GET', '/attribute-filter-rules');
        $this->assertResponseStatusCodeSame(401);
        $this->api('GET', '/attribute-filter-rules/'.$rule->getId());
        $this->assertResponseStatusCodeSame(401);
        $this->postRule(null, []);
        $this->assertResponseStatusCodeSame(401);
        $this->api('DELETE', '/attribute-filter-rules/'.$rule->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreate(): void
    {
        $this->setUpScene();

        // (group targets are not exercised: the Keycloak test mock does not resolve groups)
        $data = $this->postRule(self::USER, [
            'userIds' => [self::OTHER, self::OTHER],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($this->workspace->getId(), $data['workspaceId']);
        $this->assertSame($this->condition, $data['condition']);
        $this->assertSame([self::OTHER], array_column($data['users'], 'id'), 'targets are deduplicated');
        $this->assertSame([], $data['groups']);
    }

    public function testARuleWithoutTargetIsAllowed(): void
    {
        $this->setUpScene();

        $data = $this->postRule(self::USER, ['userIds' => null]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([], $data['users']);
        $this->assertSame([], $data['groups']);
    }

    public function testCreateRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();

        $this->postRule(self::OTHER, []);
        $this->assertResponseStatusCodeSame(403);

        $this->grantOnWorkspace(self::OTHER, $this->workspace, PermissionInterface::EDIT);
        $this->postRule(self::OTHER, []);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateValidation(): void
    {
        $this->setUpScene();

        $this->postRule(self::USER, ['condition' => 'this is ( not AQL']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'condition']]]);
    }

    public function testCreateInAnUnknownWorkspaceIsNotFound(): void
    {
        $this->markTestIncomplete('BUG: POST /attribute-filter-rules with an unknown "workspaceId" answers 500: AttributeFilterRuleInputTransformer calls DoctrineUtil::findStrict() without $throw404 (src/Api/InputTransformer/AttributeFilterRuleInputTransformer.php:34)');

        $this->setUpScene();

        $this->postRule(self::USER, ['workspaceId' => '9b2a7f5e-0000-4000-8000-000000000000']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateWithoutWorkspaceIsABadRequest(): void
    {
        $this->markTestIncomplete('BUG: POST /attribute-filter-rules without "workspaceId" answers 500: AttributeFilterRuleInputTransformer throws a plain \InvalidArgumentException (src/Api/InputTransformer/AttributeFilterRuleInputTransformer.php:38) instead of a BadRequestHttpException');

        $this->setUpScene();

        $this->postRule(self::USER, ['workspaceId' => null]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetItemRequiresTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $rule = $this->createRule();
        $iri = '/attribute-filter-rules/'.$rule->getId();

        $this->api('GET', $iri, self::USER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'id' => $rule->getId(),
            'workspaceId' => $this->workspace->getId(),
            'condition' => $this->condition,
            'users' => [['id' => self::OTHER]],
        ]);

        // Even the targeted user cannot read it
        $this->api('GET', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListFilterByWorkspace(): void
    {
        $this->setUpScene();
        $rule = $this->createRule();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $otherRule = $this->createRule($otherWorkspace);

        $this->assertEqualsCanonicalizing([$rule->getId(), $otherRule->getId()], $this->listIds(self::USER));
        $this->assertSame([$otherRule->getId()], $this->listIds(self::USER, ['workspaceId' => $otherWorkspace->getId()]));
    }

    public function testListIsRestrictedToEditableWorkspaces(): void
    {
        $this->markTestIncomplete('BUG: GET /attribute-filter-rules returns the rules of every workspace (targets and conditions included) to any authenticated user: AttributeFilterRuleCollectionProvider applies no permission check (src/Api/Provider/AttributeFilterRuleCollectionProvider.php:23)');

        $this->setUpScene();
        $rule = $this->createRule();
        $foreignRule = $this->createRule($this->createOtherWorkspace());

        $this->assertSame([$rule->getId()], $this->listIds(self::USER));
        $this->assertSame([], $this->listIds(self::OTHER));
        $this->assertSame([], $this->listIds(self::OTHER, ['workspaceId' => $this->workspace->getId()]));
        $this->assertEqualsCanonicalizing([$rule->getId(), $foreignRule->getId()], $this->listIds(self::ADMIN));
    }

    public function testUpdate(): void
    {
        $this->setUpScene();
        $rule = $this->createRule(null, [self::OTHER]);
        $iri = '/attribute-filter-rules/'.$rule->getId();
        $newCondition = sprintf('@tag != "%s"', $this->findOrCreateTagByName('foo', $this->workspace)->getId());

        // Omitted targets are kept
        $this->api('PUT', $iri, self::USER, ['condition' => $newCondition]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'condition' => $newCondition,
            'users' => [['id' => self::OTHER]],
        ]);

        // Sent targets replace the previous ones
        $response = $this->api('PUT', $iri, self::USER, ['userIds' => [self::ADMIN]]);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame([self::ADMIN], array_column($data['users'], 'id'));
        $this->assertSame([], $data['groups']);
        $this->assertSame($newCondition, $data['condition']);
    }

    public function testWritesRequireTheWorkspaceEditPermission(): void
    {
        $this->setUpScene();
        $rule = $this->createRule();
        $iri = '/attribute-filter-rules/'.$rule->getId();

        $this->api('PUT', $iri, self::OTHER, ['userIds' => []]);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->api('DELETE', $iri, self::USER);
        $this->assertResponseStatusCodeSame(204);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(AttributeFilterRule::class, $rule->getId()));
    }

    public function testARuleCannotBeMovedToANonEditableWorkspace(): void
    {
        $this->setUpScene();
        $rule = $this->createRule();
        $foreignWorkspace = $this->createOtherWorkspace();

        $this->api('PUT', '/attribute-filter-rules/'.$rule->getId(), self::USER, [
            'workspaceId' => $foreignWorkspace->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        self::getEntityManager()->clear();
        $this->assertSame($this->workspace->getId(), self::getEntityManager()->find(AttributeFilterRule::class, $rule->getId())->getWorkspaceId());
    }
}
