<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Security\RemoteAuthenticatorClientTestMock;
use App\Entity\Asset;
use App\Entity\Commit;

class CommitTest extends AbstractTestCase
{
    public function testGetCommitOK(): void
    {
        [$commitId, $assetId] = $this->createCommit();

        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'GET',
            '/commits/'.$commitId
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals('/assets/'.$assetId, $json['assets'][0]);
    }

    public function testGetCommitListOK(): void
    {
        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'GET',
            '/commits'
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertTrue(is_array($json), 'Not an array');
        $this->assertTrue(empty($json), 'Not empty');
    }

    public function testGetCommittListWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/commits');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetCommittListWithInvalidToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/commits');
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createCommit(): array
    {
        $commit = new Commit();
        $asset = new Asset();
        $asset->setMimeType('image/jpeg');

        $asset->setCommit($commit);
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId('user_id');

        $commit->getAssets()->add($asset);
        $commit->setTotalSize(42);
        $commit->setUserId('user_id');
        $commit->setToken('secret_token');

        $em = $this->getEntityManager();
        $em->persist($asset);
        $em->persist($commit);
        $em->flush();

        return [$commit->getId(), $asset->getId()];
    }
}
