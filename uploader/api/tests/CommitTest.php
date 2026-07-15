<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Commit;

class CommitTest extends AbstractUploaderTestCase
{
    public function testGetCommitOK(): void
    {
        [$commitId, $assetId] = $this->createCommit();

        $client = static::createClient();
        $response = $client->request(
            'GET',
            '/commits/'.$commitId,
            [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                ],
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();

        $this->assertEquals('application/ld+json; charset=utf-8', $response->getHeaders()['content-type'][0]);
        $this->assertEquals($assetId, $data['assets'][0]['id']);
    }

    public function testGetCommitListOK(): void
    {
        $client = static::createClient();
        $response = $client->request(
            'GET',
            '/commits',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                ],
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertEquals('application/ld+json; charset=utf-8', $response->getHeaders()['content-type'][0]);
        $this->assertEmpty($data['hydra:member']);
    }

    public function testGetCommitListWithAnonymousUser(): void
    {
        $client = static::createClient();
        $response = $client->request(
            'GET',
            '/commits',
        );
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetCommitListWithInvalidToken(): void
    {
        $client = static::createClient();
        $response = $client->request(
            'GET',
            '/commits',
            [
                'headers' => [
                    'Authorization' => 'Bearer invalid_token',
                ],
            ]
        );
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createCommit(): array
    {
        $target = $this->getOrCreateDefaultTarget();
        $commit = new Commit();
        $commit->setTarget($target);
        $asset = new Asset();
        $asset->setTarget($target);
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
