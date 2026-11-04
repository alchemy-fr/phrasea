<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\DeleteAsset;
use App\Consumer\Handler\PublicationArchiveRequest;
use App\Consumer\Handler\PublicationArchiveRequestHandler;
use App\Entity\Publication;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class PublicationArchiveTest extends AbstractExposeTestCase
{
    public function testDownloadBuildsTheArchiveOnce(): void
    {
        $publication = $this->createPublication(['title' => 'My Publication']);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(302, $response->getStatusCode());

        $publication = $this->reloadPublication($id);
        $archivePath = $publication->getArchivePath();
        $this->assertNotNull($archivePath);
        $this->assertNotNull($publication->getArchiveHash());
        $this->assertStringStartsWith('archives/'.$id.'/'.$publication->getArchiveHash().'/', $archivePath);
        $this->assertStringEndsWith('/My-Publication.zip', $archivePath);
        $this->assertStringContainsString(rawurlencode(basename($archivePath)), $response->headers->get('Location'));

        $this->assertSame([
            'Foo (2).jpeg',
            'Foo.jpeg',
        ], $this->getArchiveEntries($archivePath));

        // A stored archive is served as is while the publication does not change.
        self::getStorageManager()->delete($archivePath);
        self::getStorageManager()->store($archivePath, 'cached');

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertSame($archivePath, $this->reloadPublication($id)->getArchivePath());
        $this->assertSame('cached', stream_get_contents(self::getStorageManager()->getStream($archivePath)));
    }

    public function testAddingAnAssetReplacesTheArchive(): void
    {
        $publication = $this->createPublication();
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(302, $response->getStatusCode());
        $previousPath = $this->reloadPublication($id)->getArchivePath();

        $this->createAsset($this->reloadPublication($id));
        $this->clearEmBeforeApiCall();

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(302, $response->getStatusCode());

        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotSame($previousPath, $archivePath);
        $this->assertCount(3, $this->getArchiveEntries($archivePath));

        $deletedPaths = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof DeleteAsset) {
                $deletedPaths[] = $message->getPath();
            }
        }
        $this->assertSame([$previousPath], $deletedPaths);
    }

    public function testDeletingThePublicationDeletesTheArchive(): void
    {
        $publication = $this->createPublication();
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertAssetFileExists($archivePath);
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'DELETE', '/publications/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertAssetFileDoesNotExist($archivePath);
    }

    public function testPublicationWithoutAssetHasNoArchive(): void
    {
        $id = $this->createPublication()->getId();

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'foo@example.com',
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testProtectedPublicationArchiveIsDenied(): void
    {
        $publication = $this->createPublication(['password' => 'xxx']);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertContains($response->getStatusCode(), [401, 403]);
        $this->assertNull($this->reloadPublication($id)->getArchivePath());
    }

    public function testArchiveRequestViaEmail(): void
    {
        $publication = $this->createPublication();
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'foo@example.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $messages = array_values(array_filter(
            array_map(fn ($envelope) => $envelope->getMessage(), $transport->getSent()),
            fn (object $message): bool => $message instanceof PublicationArchiveRequest,
        ));
        $this->assertCount(1, $messages);
        $this->assertNull($this->reloadPublication($id)->getArchivePath());

        /** @var PublicationArchiveRequestHandler $handler */
        $handler = self::getService(PublicationArchiveRequestHandler::class);
        $handler($messages[0]);

        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotNull($archivePath);
        $this->assertCount(2, $this->getArchiveEntries($archivePath));
    }

    private function reloadPublication(string $id): Publication
    {
        $em = self::getEntityManager();
        $em->clear();

        $publication = $em->find(Publication::class, $id);
        $this->assertInstanceOf(Publication::class, $publication);

        return $publication;
    }

    private function interceptTransport(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p1');

        return $transport->intercept();
    }

    /**
     * @return list<string>
     */
    private function getArchiveEntries(string $archivePath): array
    {
        $localFile = tempnam(sys_get_temp_dir(), 'archive-test');
        try {
            file_put_contents($localFile, self::getStorageManager()->getStream($archivePath));

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($localFile));
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entries[] = $zip->getNameIndex($i);
            }
            $zip->close();
        } finally {
            unlink($localFile);
        }
        sort($entries);

        return $entries;
    }
}
