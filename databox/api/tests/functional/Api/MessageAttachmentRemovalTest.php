<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Test\Client;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Discussion\Message;
use App\File\OrphanFileRemover;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Removing attachments when editing a message (`PUT /messages/{id}`). The
 * attachments are annotations, or a file from a completed upload: linked to
 * its message while attached, deleted with its removal.
 */
class MessageAttachmentRemovalTest extends AbstractSearchTestCase
{
    use MultipartUploadTestTrait;

    public function testRemoveAttachmentsById(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Look at this', ['an1', 'an2'])['id'];

        $data = $this->putMessage($client, $id, ['removeAttachments' => ['an1']]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Look at this', $data['content']);
        $this->assertSame(['an2'], $this->getAttachmentIds($data));

        // The content alone is left
        $data = $this->putMessage($client, $id, ['removeAttachments' => ['an2']]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->getAttachmentIds($data));
    }

    public function testAnAttachedFileIsLinkedToItsMessageUntilRemoved(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        [$id, $fileId] = $this->postFileMessage($client);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame([$fileId], $this->getFileIds($em->find(Message::class, $id)));
        $this->assertFalse(self::getService(OrphanFileRemover::class)->removeIfOrphan($fileId));

        $this->putMessage($client, $id, ['removeAttachments' => [$fileId]]);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $this->assertSame([], $this->getFileIds($em->find(Message::class, $id)));
        $this->assertNull($em->find(File::class, $fileId));
    }

    public function testDeletingAMessageRemovesItsFiles(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        [$id, $fileId] = $this->postFileMessage($client);

        $client->request('DELETE', '/messages/'.$id, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID)],
        ]);
        $this->assertResponseStatusCodeSame(204);

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(File::class, $fileId));
    }

    public function testEditContentKeepsAttachments(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Before', ['an1'])['id'];

        $data = $this->putMessage($client, $id, ['content' => 'After']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('After', $data['content']);
        $this->assertSame(['an1'], $this->getAttachmentIds($data));
    }

    public function testCannotEmptyAMessage(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, '', ['an1'])['id'];

        $this->putMessage($client, $id, ['removeAttachments' => ['an1']]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testOthersCannotRemoveAttachments(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $id = $this->postMessage($client, 'Mine', ['an1'])['id'];

        $this->putMessage($client, $id, ['removeAttachments' => ['an1']], KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @param string[] $annotationIds
     */
    private function postMessage(Client $client, string $content, array $annotationIds, array $attachments = []): array
    {
        $asset = self::getEntityManager()->getRepository(Asset::class)->findOneBy(['key' => 'foo']);

        $response = $client->request('POST', '/messages', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'threadKey' => 'asset:'.$asset->getId(),
                'content' => $content,
                'attachments' => [
                    ...array_map(fn (string $id): array => [
                        'type' => 'annotation',
                        'content' => json_encode(['id' => $id, 'type' => 'point'], JSON_THROW_ON_ERROR),
                    ], $annotationIds),
                    ...$attachments,
                ],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        return $response->toArray();
    }

    /**
     * @return array{0: string, 1: string} the message and file IDs
     */
    private function postFileMessage(Client $client): array
    {
        $upload = $this->createUpload('hello', 'note.txt', 'text/plain', KeycloakClientTestMock::ADMIN_UID);
        $data = $this->postMessage($client, 'See attached', [], [
            ['type' => 'file', 'multipart' => self::multipartPayload($upload)],
        ]);

        return [$data['id'], $this->getAttachmentIds($data)[0]];
    }

    private function putMessage(Client $client, string $id, array $data, string $userId = KeycloakClientTestMock::ADMIN_UID): array
    {
        return $client->request('PATCH', '/messages/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
            'json' => $data,
        ])->toArray(false);
    }

    /**
     * @return string[]
     */
    private function getFileIds(Message $message): array
    {
        return $message->getFiles()->map(fn (File $file): string => $file->getId())->getValues();
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
