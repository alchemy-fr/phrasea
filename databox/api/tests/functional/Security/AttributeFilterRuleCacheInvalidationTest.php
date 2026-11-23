<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The rules and the read decisions are cached for the request (or the message): writing a rule
 * through the EntityManager, whatever the caller, must make the next vote see it.
 * Every step runs in the same kernel, with no HTTP request in between, which would reset the caches.
 */
class AttributeFilterRuleCacheInvalidationTest extends AbstractDataboxTestCase
{
    private const string HIDING_CONDITION = 'category = "press"';
    private const string PASSING_CONDITION = 'category = "internal"';

    private ?string $workspaceId = null;
    private ?string $assetId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = $this->createWorkspace(['public' => true]);
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Internal',
            'public' => true,
            'attributes' => [[
                'definition' => $definition,
                'value' => 'internal',
            ]],
        ]);
        $this->workspaceId = $workspace->getId();
        $this->assetId = $asset->getId();

        $user = new JwtUser('JWT', KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::USER_UID, ['ROLE_USER']);
        self::getService(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    public function testCreatingARuleHidesTheAsset(): void
    {
        $this->assertReadable(true);

        $this->persistRule(self::HIDING_CONDITION, [KeycloakClientTestMock::USER_UID]);

        $this->assertReadable(false);
    }

    public function testUpdatingTheConditionOfARuleShowsTheAsset(): void
    {
        $ruleId = $this->persistRule(self::HIDING_CONDITION, [KeycloakClientTestMock::USER_UID]);
        $this->assertReadable(false);

        $em = self::getEntityManager();
        $em->find(AttributeFilterRule::class, $ruleId)->setCondition(self::PASSING_CONDITION);
        $em->flush();

        $this->assertReadable(true);
    }

    public function testDeletingARuleShowsTheAsset(): void
    {
        $ruleId = $this->persistRule(self::HIDING_CONDITION, [KeycloakClientTestMock::USER_UID]);
        $this->assertReadable(false);

        $em = self::getEntityManager();
        $em->remove($em->find(AttributeFilterRule::class, $ruleId));
        $em->flush();

        $this->assertReadable(true);
    }

    public function testTargetingTheUserHidesTheAsset(): void
    {
        // A rule of the user lets the asset pass: the decision is cached
        $this->persistRule(self::PASSING_CONDITION, [KeycloakClientTestMock::USER_UID]);
        $ruleId = $this->persistRule(self::HIDING_CONDITION, [KeycloakClientTestMock::OTHER_USER_UID]);
        $this->assertReadable(true);

        // Only the targets change
        $em = self::getEntityManager();
        $em->find(AttributeFilterRule::class, $ruleId)->setTargets([KeycloakClientTestMock::USER_UID], []);
        $em->flush();

        $this->assertReadable(false);
    }

    public function testUntargetingTheUserShowsTheAsset(): void
    {
        $ruleId = $this->persistRule(self::HIDING_CONDITION, [KeycloakClientTestMock::USER_UID]);
        $this->assertReadable(false);

        $em = self::getEntityManager();
        $em->find(AttributeFilterRule::class, $ruleId)->setTargets([KeycloakClientTestMock::OTHER_USER_UID], []);
        $em->flush();

        $this->assertReadable(true);
    }

    /**
     * @param string[] $userIds
     */
    private function persistRule(string $condition, array $userIds): string
    {
        $em = self::getEntityManager();
        $rule = new AttributeFilterRule();
        $rule->setWorkspace($em->getReference(Workspace::class, $this->workspaceId));
        $rule->setCondition($condition);
        $rule->setTargets($userIds, []);
        $em->persist($rule);
        $em->flush();

        return $rule->getId();
    }

    private function assertReadable(bool $expected): void
    {
        $em = self::getEntityManager();
        // Reloaded with its attributes, which the asset document is built from
        $em->clear();
        $asset = $em->find(Asset::class, $this->assetId);

        $this->assertSame($expected, self::getService(Security::class)->isGranted(AbstractVoter::READ, $asset));
    }
}
