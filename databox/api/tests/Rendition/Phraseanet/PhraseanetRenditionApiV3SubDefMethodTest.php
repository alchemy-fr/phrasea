<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Workspace;
use App\External\PhraseanetApiClientFactory;
use App\Tests\FileUploadTrait;
use App\Tests\Mock\EventProducerMock;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class PhraseanetRenditionApiV3SubDefMethodTest extends ApiTestCase
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

    public function testApiV3SubDefIsTriggered(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();

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
        $workspace->setPhraseanetBaseUrl('https://foo.bar');
        $workspace->setPhraseanetToken('baz');
        $workspace->setPhraseanetRenditionMethod(Workspace::PHRASEANET_RENDITION_METHOD_SUBDEF_V3_API);
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
        self::assertEquals(PhraseanetGenerateAssetRenditionsHandler::EVENT, $eventMessage->getType());
        $this->consumeEvent($eventMessage);

        $transaction = $clientFactory->shiftHistory();
        /** @var Request $trRequest */
        $trRequest = $transaction['request'];
        self::assertEquals('POST', $trRequest->getMethod());
        self::assertEquals('OAuth baz', $trRequest->getHeaders()['Authorization'][0]);
        self::assertEquals('https://foo.bar/api/v3/subdefs_service/', (string) $trRequest->getUri());
        $phraseanetBodyData = json_decode($trRequest->getBody()->getContents(), true);
        self::assertArraySubset([
            'databoxId' => 2,
            'source' => [],
            'destination' => [],
        ], $phraseanetBodyData);
        $jwt = $phraseanetBodyData['destination']['payload']['token'];

        // Call from Phraseanet without token
        $apiClient->request('POST', '/phraseanet/renditions/incoming/'.$assetId);
        $this->assertResponseStatusCodeSame(401);

        // Call from Phraseanet with invalid token
        $apiClient->request('POST', '/phraseanet/renditions/incoming/'.$assetId, [
            'json' => [
                'token' => 'invalid-token',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
        // Call from Phraseanet with valid token
        $response = $apiClient->request('POST', '/phraseanet/renditions/incoming/'.$assetId, [
            'json' => [
                'token' => $jwt,
                'file_info' => [
                    'name' => 'thumbnail',
                ],
            ],
            'extra' => [
                'files' => [
                    'file' => $this->createUploadedFile(__DIR__.'/../../fixtures/files/alchemy.png'),
                ],
            ],
        ]);
        $data = json_decode($response->getContent(), true);
        $this->assertResponseStatusCodeSame(200);

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
                'size' => 4113,
                'type' => 'image/png',
            ],
        ]);
        $this->assertMatchesRegularExpression('#https://minio\.phrasea\.local/databox/[^.]+\.png\?#', $data['thumbnail']['url']);
    }
}
