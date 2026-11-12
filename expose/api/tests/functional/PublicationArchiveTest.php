<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Archive\PublicationArchiver;
use App\Consumer\Handler\DeleteAsset;
use App\Consumer\Handler\PublicationArchiveRequest;
use App\Consumer\Handler\PublicationArchiveRequestHandler;
use App\Entity\Publication;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class PublicationArchiveTest extends AbstractExposeTestCase
{
    private const array HTML = ['HTTP_ACCEPT' => 'text/html'];

    public function testDownloadBuildsTheArchiveOnce(): void
    {
        $publication = $this->createPublication(['title' => 'My Publication', 'downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $archiveUrl = $this->getArchiveDownloadUrl($id);
        $transport = $this->interceptTransport();

        $response = $this->request(null, 'GET', $archiveUrl, [], [], self::HTML);
        $this->assertPreparing($response);
        $messages = $this->getArchiveRequests($transport);
        $this->assertCount(1, $messages);
        $this->assertSame($id, $messages[0]->getPublicationId());
        $this->assertNull($messages[0]->getDownloadRequestId());
        $this->assertNull($this->reloadPublication($id)->getArchivePath());

        // The readers asking while the archive is built do not ask for another build.
        $response = $this->request(null, 'GET', $archiveUrl);
        $this->assertPreparing($response);
        $this->assertCount(0, $this->getArchiveRequests($transport));

        $this->handle($messages[0]);

        $publication = $this->reloadPublication($id);
        $archivePath = $publication->getArchivePath();
        $this->assertNotNull($archivePath);
        $this->assertNotNull($publication->getArchiveHash());
        $this->assertStringStartsWith('archives/'.$id.'/'.$publication->getArchiveHash().'/', $archivePath);
        $this->assertStringEndsWith('/My-Publication.zip', $archivePath);
        $this->assertSame([
            'Foo (2).jpeg',
            'Foo.jpeg',
        ], $this->getArchiveEntries($archivePath));

        $response = $this->request(null, 'GET', $archiveUrl, [], [], self::HTML);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString(rawurlencode(basename($archivePath)), $response->headers->get('Location'));

        // A client asking for JSON cannot follow the redirection without downloading the archive.
        $response = $this->request(null, 'GET', $archiveUrl);
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ready', $json['status']);
        $this->assertStringContainsString(rawurlencode(basename($archivePath)), $json['url']);

        // A stored archive is served as is while the publication does not change.
        self::getStorageManager()->delete($archivePath);
        self::getStorageManager()->store($archivePath, 'cached');

        $response = $this->request(null, 'GET', $archiveUrl, [], [], self::HTML);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertCount(0, $this->getArchiveRequests($transport));
        $this->assertSame($archivePath, $this->reloadPublication($id)->getArchivePath());
        $this->assertSame('cached', stream_get_contents(self::getStorageManager()->getStream($archivePath)));
    }

    public function testAddingAnAssetReplacesTheArchive(): void
    {
        $publication = $this->createPublication(['downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $archiveUrl = $this->getArchiveDownloadUrl($id);
        $this->buildArchive($id);
        $previousPath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotNull($previousPath);

        $this->createAsset($this->reloadPublication($id));
        $this->clearEmBeforeApiCall();

        $transport = $this->interceptTransport();
        $this->assertPreparing($this->request(null, 'GET', $archiveUrl));
        $messages = $this->getArchiveRequests($transport);
        $this->assertCount(1, $messages);

        $transport->reset();
        $this->handle($messages[0]);

        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotSame($previousPath, $archivePath);
        $this->assertCount(3, $this->getArchiveEntries($archivePath));
        $this->assertSame([$previousPath], $this->getDeletedPaths($transport));

        $this->assertSame(302, $this->request(null, 'GET', $archiveUrl, [], [], self::HTML)->getStatusCode());
    }

    public function testAnObsoleteBuildDoesNotReplaceAMoreRecentArchive(): void
    {
        $publication = $this->createPublication(['downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $this->buildArchive($id);
        $publication = $this->reloadPublication($id);
        $recentPath = $publication->getArchivePath();
        $recentHash = $publication->getArchiveHash();

        // A build started before the recent archive was recorded (no archive then) ends now.
        $transport = $this->interceptTransport();
        $obsoletePath = 'archives/'.$id.'/obsolete/Foo.zip';
        self::getStorageManager()->store($obsoletePath, 'obsolete');
        $recorded = self::getService(PublicationArchiver::class)->recordArchive($publication, $obsoletePath, 'obsolete', null);

        $this->assertFalse($recorded);
        $publication = $this->reloadPublication($id);
        $this->assertSame($recentPath, $publication->getArchivePath());
        $this->assertSame($recentHash, $publication->getArchiveHash());
        $this->assertSame([$obsoletePath], $this->getDeletedPaths($transport));

        // The build of the same content as the recorded archive keeps it.
        $transport->reset();
        $recorded = self::getService(PublicationArchiver::class)->recordArchive($publication, $recentPath, $recentHash, null);
        $this->assertFalse($recorded);
        $this->assertSame([], $this->getDeletedPaths($transport));
    }

    public function testDeletingThePublicationDeletesTheArchive(): void
    {
        $publication = $this->createPublication(['downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        // Not intercepted, the build request is handled during the request.
        $this->assertPreparing($this->request(null, 'GET', $this->getArchiveDownloadUrl($id)));
        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotNull($archivePath);
        $this->assertAssetFileExists($archivePath);
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'DELETE', '/publications/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertAssetFileDoesNotExist($archivePath);
    }

    public function testPublicationWithoutAssetHasNoArchive(): void
    {
        $id = $this->createPublication(['downloadEnabled' => true])->getId();

        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'foo@example.com',
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testProtectedPublicationArchiveIsDenied(): void
    {
        $publication = $this->createPublication(['password' => 'xxx', 'downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'GET', '/publications/'.$id.'/archive');
        $this->assertContains($response->getStatusCode(), [401, 403]);
        $this->assertCount(0, $this->getArchiveRequests($transport));
        $this->assertNull($this->reloadPublication($id)->getArchivePath());
    }

    public function testArchiveRequestViaEmail(): void
    {
        $publication = $this->createPublication(['downloadEnabled' => true]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'foo@example.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $messages = $this->getArchiveRequests($transport);
        $this->assertCount(1, $messages);
        $this->assertSame($id, $messages[0]->getPublicationId());
        $this->assertNotNull($messages[0]->getDownloadRequestId());
        $this->assertNull($this->reloadPublication($id)->getArchivePath());

        $this->handle($messages[0]);

        $archivePath = $this->reloadPublication($id)->getArchivePath();
        $this->assertNotNull($archivePath);
        $this->assertCount(2, $this->getArchiveEntries($archivePath));
    }

    private function assertPreparing(Response $response): void
    {
        $this->assertSame(202, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(['status' => 'preparing'], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function buildArchive(string $id): void
    {
        $this->handle(new PublicationArchiveRequest($id));
        $this->assertNotNull($this->reloadPublication($id)->getArchivePath());
    }

    private function handle(PublicationArchiveRequest $message): void
    {
        /** @var PublicationArchiveRequestHandler $handler */
        $handler = self::getService(PublicationArchiveRequestHandler::class);
        $handler($message);
    }

    /**
     * @return list<PublicationArchiveRequest>
     */
    private function getArchiveRequests(InMemoryTransport $transport): array
    {
        return array_values(array_filter(
            array_map(fn ($envelope) => $envelope->getMessage(), $transport->getSent()),
            fn (object $message): bool => $message instanceof PublicationArchiveRequest,
        ));
    }

    /**
     * @return list<string>
     */
    private function getDeletedPaths(InMemoryTransport $transport): array
    {
        $deletedPaths = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof DeleteAsset) {
                $deletedPaths[] = $message->getPath();
            }
        }

        return $deletedPaths;
    }

    private function getArchiveDownloadUrl(string $id): string
    {
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsString($json['archiveDownloadUrl'] ?? null);

        return $json['archiveDownloadUrl'];
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
