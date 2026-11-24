<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository\Core;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Repository\Core\WorkspaceRepository;
use App\Service\Workspace\TermsManager;
use App\Tests\Functional\AbstractDataboxTestCase;

class WorkspaceRepositoryTest extends AbstractDataboxTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;

    public function testWorkspaceWithUnsignedTermsIsNotReadable(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => 'another_owner']);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW);
        $id = $workspace->getId();

        $this->assertContains($id, $this->getReadableIds(self::USER), 'No terms');

        $v1 = $this->updateTerms($workspace, 'Terms v1');
        $this->assertNotContains($id, $this->getReadableIds(self::USER), 'Unsigned terms');
        $this->assertContains($id, $this->getReadableIds(self::USER, isAdmin: true), 'Admin is not gated');

        $this->getTermsManager()->sign($v1, self::USER);
        $this->assertContains($id, $this->getReadableIds(self::USER), 'Signed terms');

        $this->updateTerms($workspace, 'Terms v2');
        $this->assertNotContains($id, $this->getReadableIds(self::USER), 'New version not signed yet');

        $this->updateTerms($workspace, '');
        $this->assertContains($id, $this->getReadableIds(self::USER), 'Cleared terms');
    }

    public function testSignatureIsPerUser(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => 'another_owner']);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW);
        $this->grantUserOnObject('user2', $workspace, PermissionInterface::VIEW);

        $terms = $this->updateTerms($workspace, 'Terms');
        $this->getTermsManager()->sign($terms, 'user2');

        $this->assertNotContains($workspace->getId(), $this->getReadableIds(self::USER));
        $this->assertContains($workspace->getId(), $this->getReadableIds('user2'));
    }

    public function testPublicWorkspaceWithUnsignedTerms(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => 'another_owner', 'public' => true, 'no_acl' => true]);
        $id = $workspace->getId();
        $this->updateTerms($workspace, 'Terms');

        $this->assertContains($id, $this->getReadableIds(null), 'Anonymous users are not gated');
        $this->assertContains($id, $this->getRepository()->getPublicWorkspaceIds());

        $this->assertNotContains($id, $this->getReadableIds(self::USER));
        $this->resetCache();
        $this->assertNotContains($id, $this->getRepository()->getPublicWorkspaceIds(self::USER));
    }

    public function testNotSharedWorkspaceIsNotReadable(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => 'another_owner']);

        $this->assertNotContains($workspace->getId(), $this->getReadableIds(self::USER));
        $this->assertContains($workspace->getId(), $this->getReadableIds(self::USER, isAdmin: true));
    }

    /**
     * @return string[]
     */
    private function getReadableIds(?string $userId, bool $isAdmin = false): array
    {
        $this->resetCache();

        return $this->getRepository()->getReadableWorkspaceIds($userId, [], $isAdmin);
    }

    private function updateTerms(Workspace $workspace, string $text): TermsVersion
    {
        $terms = $this->getTermsManager()->updateTerms($workspace, $text);
        self::getEntityManager()->flush();
        $this->assertInstanceOf(TermsVersion::class, $terms);

        return $terms;
    }

    private function resetCache(): void
    {
        /** @var TemporaryCacheFactory $cacheFactory */
        $cacheFactory = self::getService(TemporaryCacheFactory::class);
        $cacheFactory->reset();
    }

    private function getRepository(): WorkspaceRepository
    {
        /** @var WorkspaceRepository $repository */
        $repository = self::getEntityManager()->getRepository(Workspace::class);

        return $repository;
    }

    private function getTermsManager(): TermsManager
    {
        /** @var TermsManager $termsManager */
        $termsManager = self::getService(TermsManager::class);

        return $termsManager;
    }
}
