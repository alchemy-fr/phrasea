<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;

class PostCommitTest extends AbstractUploaderTestCase
{
    public function testCommit(): void
    {
        $target = $this->createTarget();
        $asset1 = $this->createAsset($target);
        $asset2 = $this->createAsset($target);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/commit', [
            'files' => [$asset1->getId(), $asset2->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertTrue(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $em = self::getEntityManager();
        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $commit = $commits[0];
        $this->assertEquals(KeycloakClientTestMock::ADMIN_UID, $commit->getUserId());
        $this->assertEquals(84, (int) $commit->getTotalSize());
        $this->assertCount(2, $commit->getAssets());
    }

    public function testCommitWithUnknownTargetSlugReturns400(): void
    {
        $target = $this->createTarget();
        $asset = $this->createAsset($target);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/commit', [
            'files' => [$asset->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
            'targetSlug' => 'unknown',
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCommitWithAnonymousUserReturns401(): void
    {
        $target = $this->createTarget();

        $response = $this->request(null, 'POST', '/commit', [
            'files' => [],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createTarget(): Target
    {
        $em = self::getEntityManager();

        $target = new Target();
        $target->setName('Commit target');
        $target->setSlug('commit-target');
        // No target URL: the commit is not pushed to a consumer
        $target->setTargetUrl(null);
        $em->persist($target);
        $em->flush();

        return $target;
    }

    private function createAsset(Target $target): Asset
    {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setMimeType('image/jpeg');
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId(KeycloakClientTestMock::ADMIN_UID);

        $em = self::getEntityManager();
        $em->persist($asset);
        $em->flush();

        return $asset;
    }
}
