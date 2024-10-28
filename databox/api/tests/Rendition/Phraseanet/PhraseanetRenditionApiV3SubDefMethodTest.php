<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use Alchemy\Workflow\Message\JobConsumer;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Phraseanet\PhraseanetApiClientFactory;
use App\Integration\Phraseanet\PhraseanetRenditionIntegration;
use App\Tests\FileUploadTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Response\MockResponse;
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

        $inMemoryTransport = $this->interceptMessengerEvents();
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
            'databoxId' => 2,
            'method' => PhraseanetRenditionIntegration::METHOD_API,
            'renditions' => [
                'thumbnail',
            ],
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
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $assetId = $json['id'];

        $envelope = $inMemoryTransport->get()[0];
        $eventMessage = $envelope->getMessage();
        self::assertInstanceOf(JobConsumer::class, $eventMessage);
        self::assertEquals(PhraseanetRenditionIntegration::getName().':'.$integration->getId().':api', $eventMessage->getJobStateId());
        $this->consumeEvent($envelope);

        self::assertEquals('POST', $mockResponse->getRequestMethod());
        $requestOptions = $mockResponse->getRequestOptions();
        self::assertEquals('Authorization: OAuth baz', $requestOptions['headers'][0]);
        self::assertEquals('https://foo.bar/api/v3/subdefs_service/', (string) $mockResponse->getRequestUrl());
        $phraseanetBodyData = json_decode($requestOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArraySubset([
            'databoxId' => 2,
            'source' => [],
            'destination' => [],
        ], $phraseanetBodyData);
        $jwt = $phraseanetBodyData['destination']['payload']['token'];

        $endpoint = sprintf('/integrations/phraseanet/%s/renditions/incoming/%s', $integration->getId(), $assetId);
        // Call from Phraseanet without token
        $apiClient->request('POST', $endpoint);
        $this->assertResponseStatusCodeSame(401);

        // Call from Phraseanet with invalid token
        $apiClient->request('POST', $endpoint, [
            'json' => [
                'token' => 'invalid-token',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
        // Call from Phraseanet with valid token
        $response = $apiClient->request('POST', $endpoint, [
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
        $this->assertResponseStatusCodeSame(200);

        $response = $apiClient->request('GET', '/assets/'.$assetId, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Dummy asset',
            'thumbnail' => [
                'file' => [
                    'size' => 4113,
                    'type' => 'image/png',
                ],
            ],
        ]);
        $this->assertMatchesRegularExpression('#https://minio\.[^/]+/databox/[^.]+\.png\?#', $data['thumbnail']['file']['url']);
    }
}
