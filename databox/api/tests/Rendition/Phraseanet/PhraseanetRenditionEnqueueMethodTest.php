<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Consumer\Handler\File\ImportRenditionHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetDownloadSubdefHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Controller\Legacy\PhraseanetController;
use App\Entity\Core\Workspace;
use App\External\PhraseanetApiClientFactory;
use App\Tests\FileUploadTrait;
use App\Tests\Mock\EventProducerMock;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class PhraseanetRenditionEnqueueMethodTest extends ApiTestCase
{
    use FixturesTrait;
    use FileUploadTrait;
    use TestServicesTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::bootKernelWithFixtures($options);

        return static::$kernel;
    }

    public function testEnqueueIsTriggered(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $apiClient->disableReboot();

        /** @var EventProducerMock $eventProducer */
        $eventProducer = self::getService(EventProducer::class);
        $eventProducer->interceptEvents();
        $em = self::getService(EntityManagerInterface::class);
        /** @var PhraseanetApiClientFactoryMock $clientFactory */
        $clientFactory = self::getService(PhraseanetApiClientFactory::class);
        $clientMock = $clientFactory->getMock();
        $clientMock->append(new Response(200));

        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);
        $workspace->setPhraseanetDataboxId(2);
        $workspace->setPhraseanetCollectionId(42);
        $workspace->setPhraseanetBaseUrl('https://foo.bar');
        $workspace->setPhraseanetToken('baz');
        $workspace->setPhraseanetRenditionMethod(Workspace::PHRASEANET_RENDITION_METHOD_ENQUEUE);
        $em->persist($workspace);
        $em->flush();

        $workspaceIri = $this->findIriBy(Workspace::class, [
            'slug' => 'test-workspace',
        ]);

        $response = $apiClient->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'title' => 'Dummy asset',
                'workspace' => $workspaceIri,
                'generateRenditions' => true,
            ],
            'extra' => [
                'files' => [
                    'file' => $this->createUploadedFile(__DIR__.'/../../fixtures/files/alchemy.png'),
                ],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
        ]);
        $json = \GuzzleHttp\json_decode($response->getContent(), true);
        $assetId = $json['id'];

        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::EVENT, $eventMessage->getType());
        $this->consumeEvent($eventMessage);

        $transaction = $clientFactory->shiftHistory();
        /** @var Request $trRequest */
        $trRequest = $transaction['request'];
        self::assertEquals('POST', $trRequest->getMethod());
        self::assertEquals('OAuth baz', $trRequest->getHeaders()['Authorization'][0]);
        self::assertEquals('https://foo.bar/api/v1/upload/enqueue/', (string)$trRequest->getUri());
        $phraseanetBodyData = json_decode($trRequest->getBody()->getContents(), true);
        self::assertArraySubset([
            'assets' => [$assetId],
            'publisher' => '4242',
            'commit_id' => $assetId,
            'base_url' => 'https://api-databox.phrasea.local/phraseanet/',
        ], $phraseanetBodyData);

        // Call from Phraseanet without token
        $apiClient->request('GET', '/phraseanet/assets/'.$assetId);
        $this->assertResponseStatusCodeSame(401);

        // Call from Phraseanet with invalid token
        $apiClient->request('GET', '/phraseanet/assets/'.$assetId, [
            'headers' => [
                'Authorization' => 'AssetToken invalidtoken',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
        // Call from Phraseanet with valid token
        $response = $apiClient->request('GET', '/phraseanet/assets/'.$assetId, [
            'headers' => [
                'Authorization' => 'AssetToken '.$phraseanetBodyData['token'],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $originalName = PhraseanetController::ASSET_NAME_PREFIX.$assetId.'.png';
        $this->assertJsonContains([
            'originalName' => $originalName,
            'formData' => [
                'collection_destination' => 42,
            ],
        ]);

        // Phraseanet creates sub definitions...
        // Then we receive webhook for each generated sub def:
        // Call from Phraseanet with valid token
        $thumbnailRemoteUrl = 'https://foo.bar/permalink/123456/thumb.jpg';
        $response = $apiClient->request('POST', '/phraseanet/events', [
            'json' => [
                'event' => 'record.subdef.created',
                'url' => 'foo.bar',
                'data' => [
                    'databox_id' => 1,
                    'record_id' => 123456,
                    'original_name' => $originalName,
                    'permalink' => $thumbnailRemoteUrl,
                    'subdef_name' => 'thumbnail',
                    'type' => 'image/jpeg',
                    'size' => 42,
                ]
            ]
        ]);
        $this->assertResponseStatusCodeSame(200);
        /** @var EventProducerMock $eventProducer */
        $eventProducer = self::getService(EventProducer::class);
        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(PhraseanetDownloadSubdefHandler::EVENT, $eventMessage->getType());
        $this->consumeEvent($eventMessage);
        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(ImportRenditionHandler::EVENT, $eventMessage->getType());

        $em = self::getService(EntityManagerInterface::class);
        $em->clear();

        $response = $apiClient->request('GET', '/assets/'.$assetId, [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
        ]);
        $data = json_decode($response->getContent(), true);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
            'thumbnail' => [
                'url' => $thumbnailRemoteUrl,
                'size' => 42,
                'type' => 'image/jpeg',
            ],
        ]);
    }
}
