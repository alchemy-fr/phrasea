<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Tag;
use App\Service\Workspace\WorkspaceDuplicateManager;
use App\Tests\Functional\AbstractDataboxTestCase;

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

    public function testDuplicateWorkspaceRemapsTagReferences(): void
    {
        $workspace = $this->createWorkspace();
        $foo = $this->findOrCreateTagByName('foo', $workspace);
        $bar = $this->findOrCreateTagByName('bar', $workspace);

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [KeycloakClientTestMock::USER_UID],
            [],
            sprintf('@tag = "%s" AND @tag NOT IN ("%s")', $foo->getId(), $bar->getId())
        );

        $em = self::getEntityManager();
        /** @var WorkspaceDuplicateManager $duplicateManager */
        $duplicateManager = self::getService(WorkspaceDuplicateManager::class);
        $newWorkspace = $duplicateManager->duplicateWorkspace($workspace, 'my-workspace-copy');
        $em->flush();
        $em->clear();

        $newTagIds = [];
        foreach ($em->getRepository(Tag::class)->findBy(['workspace' => $newWorkspace->getId()]) as $tag) {
            $newTagIds[$tag->getName()] = $tag->getId();
        }
        $this->assertCount(2, $newTagIds);

        /** @var AttributeFilterRule[] $rules */
        $rules = $em->getRepository(AttributeFilterRule::class)->findBy(['workspace' => $newWorkspace->getId()]);
        $this->assertCount(1, $rules);
        $this->assertSame(
            sprintf('@tag = "%s" AND @tag NOT IN ("%s")', $newTagIds['foo'], $newTagIds['bar']),
            $rules[0]->getCondition()
        );
        $this->assertSame([KeycloakClientTestMock::USER_UID], $rules[0]->getUserIds());
    }
}
