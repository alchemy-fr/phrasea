<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\AttributeFilterRule;
use App\Service\Workspace\WorkspaceDuplicateManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AttributeFilterRuleTest extends AbstractDataboxTestCase
{
    public function testCreateRuleWithoutConditionIsRejected(): void
    {
        $workspace = $this->createWorkspace([
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        static::createClient()->request('POST', '/attribute-filter-rules', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'workspaceId' => $workspace->getId(),
                'userIds' => [KeycloakClientTestMock::USER_UID],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProvider('getUnsupportedConditions')]
    public function testCreateRuleWithAFieldARuleCannotUseIsRejected(string $condition): void
    {
        $workspace = $this->createWorkspace([
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        static::createClient()->request('POST', '/attribute-filter-rules', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'workspaceId' => $workspace->getId(),
                'userIds' => [KeycloakClientTestMock::USER_UID],
                'condition' => $condition,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, self::getEntityManager()->getRepository(AttributeFilterRule::class)->count([]));
    }

    public static function getUnsupportedConditions(): array
    {
        return [
            'similar' => ['@similar = "f2b2a4b0-1b8e-4d6c-9d43-7b8a3f1f5e11"'],
            'score' => ['@score > 1'],
            'position' => ['@position = 1'],
            'nested' => ['@privacy = 5 OR (@similar = "f2b2a4b0-1b8e-4d6c-9d43-7b8a3f1f5e11")'],
        ];
    }

    public function testDuplicateWorkspaceRemapsEntityReferences(): void
    {
        $workspace = $this->createWorkspace();
        $foo = $this->findOrCreateTagEntity('foo', $workspace);
        $bar = $this->findOrCreateTagEntity('bar', $workspace);

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [KeycloakClientTestMock::USER_UID],
            [],
            sprintf('tags = "%s" AND tags NOT IN ("%s")', $foo->getId(), $bar->getId())
        );

        $em = self::getEntityManager();
        /** @var WorkspaceDuplicateManager $duplicateManager */
        $duplicateManager = self::getService(WorkspaceDuplicateManager::class);
        $newWorkspace = $duplicateManager->duplicateWorkspace($workspace, 'my-workspace-copy');
        $em->flush();
        $em->clear();

        $newTagIds = [];
        foreach ($em->getRepository(AttributeEntity::class)->findBy(['workspace' => $newWorkspace->getId()]) as $tag) {
            $newTagIds[$tag->getValue()] = $tag->getId();
        }
        $this->assertCount(2, $newTagIds);

        /** @var AttributeFilterRule[] $rules */
        $rules = $em->getRepository(AttributeFilterRule::class)->findBy(['workspace' => $newWorkspace->getId()]);
        $this->assertCount(1, $rules);
        $this->assertSame(
            sprintf('tags = "%s" AND tags NOT IN ("%s")', $newTagIds['foo'], $newTagIds['bar']),
            $rules[0]->getCondition()
        );
        $this->assertSame([KeycloakClientTestMock::USER_UID], $rules[0]->getUserIds());
    }
}
