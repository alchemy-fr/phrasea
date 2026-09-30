<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Asset;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Removing attachments when editing a message (`PUT /messages/{id}`). The
 * attachments are annotations: a file one needs a completed upload, its
 * removal (the `File` deleted with it) goes through the same partition.
 */
class MessageAttachmentRemovalTest extends AbstractSearchTestCase
{
    public function testRemoveAttachmentsById(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Look at this', ['an1', 'an2']);

        $data = $this->putMessage($client, $id, ['removeAttachments' => ['an1']]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Look at this', $data['content']);
        $this->assertSame(['an2'], $this->getAttachmentIds($data));

        // The content alone is left
        $data = $this->putMessage($client, $id, ['removeAttachments' => ['an2']]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->getAttachmentIds($data));
    }

    public function testEditContentKeepsAttachments(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Before', ['an1']);

        $data = $this->putMessage($client, $id, ['content' => 'After']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('After', $data['content']);
        $this->assertSame(['an1'], $this->getAttachmentIds($data));
    }

    public function testCannotEmptyAMessage(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, '', ['an1']);

        $this->putMessage($client, $id, ['removeAttachments' => ['an1']]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testOthersCannotRemoveAttachments(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Mine', ['an1']);

        $this->putMessage($client, $id, ['removeAttachments' => ['an1']], KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @param string[] $annotationIds
     */
    private function postMessage(Client $client, string $content, array $annotationIds): string
    {
        $asset = self::getEntityManager()->getRepository(Asset::class)->findOneBy(['key' => 'foo']);

        $response = $client->request('POST', '/messages', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'threadKey' => 'asset:'.$asset->getId(),
                'content' => $content,
                'attachments' => array_map(fn (string $id): array => [
                    'type' => 'annotation',
                    'content' => json_encode(['id' => $id, 'type' => 'point'], JSON_THROW_ON_ERROR),
                ], $annotationIds),
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        return $response->toArray()['id'];
    }

    private function putMessage(Client $client, string $id, array $data, string $userId = KeycloakClientTestMock::ADMIN_UID): array
    {
        return $client->request('PUT', '/messages/'.$id, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
            'json' => $data,
        ])->toArray(false);
    }

    /**
     * @return string[]
     */
    private function getAttachmentIds(array $message): array
    {
        return array_map(
            fn (array $a): string => json_decode($a['content'], true, flags: JSON_THROW_ON_ERROR)['id'],
            $message['attachments'] ?? [],
        );
    }
}
