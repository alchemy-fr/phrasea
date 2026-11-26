<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Consumer\Handler\AssetConsumerNotify;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Consumer\TargetPrivateOrigins;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\CommitManager;
use App\Tests\Functional\AbstractUploaderTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AssetConsumerNotifyHandlerTest extends AbstractUploaderTestCase
{
    public function testAssetConsumerNotify(): void
    {
        $accessToken = 'secret_token';
        $uploaderUrl = 'http://localhost:8080';

        $expectedRequests = [
            function ($method, $url, $options): MockResponse {
                $this->assertEquals('http://localhost/api/v1/upload/enqueue/', $url);
                $postBody = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
                $this->assertArrayHasKey('assets', $postBody);
                $this->assertArrayHasKey('commit_id', $postBody);
                $this->assertCount(2, $postBody['assets']);
                $this->assertEquals('a_token', $postBody['token']);
                $this->assertMatchesUuid($postBody['commit_id']);
                $this->assertEquals('http://localhost:8080', $postBody['base_url']);
                $this->assertSame('POST', $method);
                $this->assertSame(60.0, $options['max_duration']);
                $this->assertSame(0, $options['max_redirects']);

                return new MockResponse('{"meta":{"api_version":"1.4.1","request":"POST \/api\/v1\/upload\/enqueue\/","response_time":"2019-06-05T16:28:24+02:00","http_code":200,"error_type":null,"error_message":null,"error_details":null,"charset":"UTF-8"},"response":{"data":{"assets":["4c097077-a26b-4af4-9a5d-b13fd4c77b3d","a134145e-9461-4f0a-8bd8-7025d31a6b8e"],"publisher":"d03fc9f6-3c6b-4428-8d6f-ba07c7c6e856","token":"a_token"}}}', [
                    'response_headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
            },
        ];

        $clientStub = new MockHttpClient($expectedRequests);

        $handler = new AssetConsumerNotifyHandler(
            $clientStub,
            $this->createEntityManager('http://localhost/api/v1/upload/enqueue/', $accessToken),
            $uploaderUrl,
            new TargetPrivateOrigins('http://localhost', null),
        );

        $message = new AssetConsumerNotify('an_ID');
        $handler($message);

        $this->assertSame(1, $clientStub->getRequestsCount());
    }

    /**
     * The commit stored in the same process (sync transport) is in the identity map, its collection of files empty.
     */
    public function testACommitStoredInTheSameProcessListsItsFiles(): void
    {
        $em = self::getEntityManager();

        $target = new Target();
        $target->setName('Notified target');
        $target->setSlug('notified-target');
        $target->setTargetUrl('http://localhost/api/v1/upload/enqueue/');
        $target->setAuthorizationKey('secret_token');
        $em->persist($target);

        $files = [];
        for ($i = 0; $i < 2; ++$i) {
            $asset = new Asset();
            $asset->setTarget($target);
            $asset->setMimeType('image/jpeg');
            $asset->setPath('a/b/c.jpeg');
            $asset->setSize(42);
            $asset->setOriginalName('foo.jpeg');
            $asset->setUserId(KeycloakClientTestMock::ADMIN_UID);
            $em->persist($asset);
            $files[] = $asset->getId();
        }
        $em->flush();

        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setFiles($files);
        $commit->setUserId(KeycloakClientTestMock::ADMIN_UID);
        $this->assertTrue(self::getService(CommitManager::class)->store($commit));

        $sent = null;
        $clientStub = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);

            return new MockResponse('{}');
        });

        $handler = new AssetConsumerNotifyHandler(
            $clientStub,
            $em,
            'http://localhost:8080',
            new TargetPrivateOrigins('http://localhost', null),
        );
        $handler(new AssetConsumerNotify($commit->getId()));

        $this->assertSame(1, $clientStub->getRequestsCount());
        $this->assertSame($commit->getId(), $sent['commit_id']);
        $this->assertEqualsCanonicalizing($files, $sent['assets']);
    }

    #[DataProvider('getPrivateTargets')]
    public function testPrivateTargetIsNotNotified(string $targetUrl): void
    {
        $clientStub = new MockHttpClient(fn (): MockResponse => new MockResponse('{}'));

        $handler = new AssetConsumerNotifyHandler(
            $clientStub,
            $this->createEntityManager($targetUrl, 'secret_token'),
            'http://localhost:8080',
            new TargetPrivateOrigins(null, null),
        );

        try {
            $handler(new AssetConsumerNotify('an_ID'));
            $this->fail('A target on the private network must not be notified');
        } catch (TransportException) {
        }

        $this->assertSame(0, $clientStub->getRequestsCount());
    }

    public static function getPrivateTargets(): array
    {
        return [
            'Private IP' => ['https://10.0.0.1/enqueue'],
            'Loopback host' => ['http://localhost/api/v1/upload/enqueue/'],
            'IPv4-mapped IPv6 address' => ['http://[::ffff:10.0.0.1]/'],
        ];
    }

    private function createEntityManager(string $targetUrl, string $accessToken): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $target = new Target();

        $commit = new Commit();
        $commit->setFormData(['foo' => 'bar']);
        $commit->setUserId('d03fc9f6-3c6b-4428-8d6f-ba07c7c6e856');
        $commit->setTarget($target);

        $commit->setToken('a_token');
        $target->setAuthorizationKey($accessToken);
        $target->setTargetUrl($targetUrl);
        $target->setAuthorizationScheme('OAuth');
        $commit->setTarget($target);

        $em->expects($this->once())
            ->method('find')
            ->willReturn($commit);

        $assetRepository = $this->createMock(AssetRepository::class);
        $assetRepository->method('getCommitAssetIds')
            ->with($commit->getId())
            ->willReturn([(new Asset())->getId(), (new Asset())->getId()]);
        $em->method('getRepository')
            ->with(Asset::class)
            ->willReturn($assetRepository);

        return $em;
    }
}
