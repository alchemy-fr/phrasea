<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use Alchemy\Workflow\Consumer\JobConsumer;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Consumer\Handler\File\ImportFileHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetDownloadSubdefHandler;
use App\Controller\Integration\PhraseanetIntegrationController;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\External\PhraseanetApiClientFactory;
use App\Integration\Phraseanet\PhraseanetRenditionIntegration;
use App\Tests\FileUploadTrait;
use App\Tests\Mock\EventProducerMock;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Response\MockResponse;
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

        $eventProducer = self::getEventProducer(true);
        $em = self::getService(EntityManagerInterface::class);
        /** @var PhraseanetApiClientFactoryMock $clientFactory */
        $clientFactory = self::getService(PhraseanetApiClientFactory::class);
        $clientMock = $clientFactory->getMock();
        $mockResponse = new MockResponse('');
        $clientMock->setResponseFactory($mockResponse);

        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setTitle('Renditions');
        $integration->setIntegration(PhraseanetRenditionIntegration::getName());
        $integration->setConfig([
            'baseUrl' => 'https://foo.bar',
            'token' => 'baz',
            'collectionId' => 42,
            'method' => PhraseanetRenditionIntegration::METHOD_ENQUEUE,
        ]);
        $em->persist($integration);

        $em->flush();

        $workspaceIri = $this->findIriBy(Workspace::class, [
            'slug' => 'test-workspace',
        ]);

        $response = $apiClient->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Dummy asset',
                'workspace' => $workspaceIri,
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
        $json = json_decode($response->getContent(), true);
        $assetId = $json['id'];

        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(JobConsumer::EVENT, $eventMessage->getType());
        self::assertEquals(PhraseanetRenditionIntegration::getName().':'.$integration->getId().':enqueue', $eventMessage->getPayload()['j']);
        $this->consumeEvent($eventMessage);

        self::assertEquals('POST', $mockResponse->getRequestMethod());
        $requestOptions = $mockResponse->getRequestOptions();
        self::assertEquals('Authorization: OAuth baz', $requestOptions['headers'][0]);
        self::assertEquals('https://foo.bar/api/v1/upload/enqueue/', $mockResponse->getRequestUrl());
        $phraseanetBodyData = json_decode($requestOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArraySubset([
            'assets' => [$assetId],
            'publisher' => '4242',
            'commit_id' => $assetId,
        ], $phraseanetBodyData);
        $this->assertMatchesRegularExpression(sprintf(
            '#https://api-databox\.[^/]+/integrations/phraseanet/%s/$#',
            preg_quote($integration->getId(), '#')
        ), $phraseanetBodyData['base_url']);

        $endpoint = sprintf('/integrations/phraseanet/%s/assets/%s', $integration->getId(), $assetId);
        // Call from Phraseanet without token
        $apiClient->request('GET', $endpoint);
        $this->assertResponseStatusCodeSame(401);

        // Call from Phraseanet with invalid token
        $apiClient->request('GET', $endpoint, [
            'headers' => [
                'Authorization' => 'AssetToken invalidtoken',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
        // Call from Phraseanet with valid token
        $response = $apiClient->request('GET', $endpoint, [
            'headers' => [
                'Authorization' => 'AssetToken '.$phraseanetBodyData['token'],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $originalName = PhraseanetIntegrationController::ASSET_NAME_PREFIX.$assetId.'.png';
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
        $apiClient->request('POST', sprintf('/integrations/phraseanet/%s/events', $integration->getId()), [
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
                ],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        /** @var EventProducerMock $eventProducer */
        $eventProducer = self::getService(EventProducer::class);
        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(PhraseanetDownloadSubdefHandler::EVENT, $eventMessage->getType());
        $this->consumeEvent($eventMessage);
        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(ImportFileHandler::EVENT, $eventMessage->getType());

        $em = self::getService(EntityManagerInterface::class);
        $em->clear();

        $response = $apiClient->request('GET', '/assets/'.$assetId, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
            'thumbnail' => [
                'file' => [
                    'url' => $thumbnailRemoteUrl,
                    'size' => 42,
                    'type' => 'image/jpeg',
                ],
            ],
        ]);
    }
}
