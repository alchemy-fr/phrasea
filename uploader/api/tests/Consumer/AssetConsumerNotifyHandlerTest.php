<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use Alchemy\ApiTest\ApiTestCase;
use App\Consumer\Handler\AssetConsumerNotify;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AssetConsumerNotifyHandlerTest extends ApiTestCase
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

                return new MockResponse('{"meta":{"api_version":"1.4.1","request":"POST \/api\/v1\/upload\/enqueue\/","response_time":"2019-06-05T16:28:24+02:00","http_code":200,"error_type":null,"error_message":null,"error_details":null,"charset":"UTF-8"},"response":{"data":{"assets":["4c097077-a26b-4af4-9a5d-b13fd4c77b3d","a134145e-9461-4f0a-8bd8-7025d31a6b8e"],"publisher":"d03fc9f6-3c6b-4428-8d6f-ba07c7c6e856","token":"a_token"}}}', [
                    'response_headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
            },
        ];

        $clientStub = new MockHttpClient($expectedRequests);

        $em = $this->createMock(EntityManagerInterface::class);

        $target = new Target();

        $commit = new Commit();
        $commit->setFormData(['foo' => 'bar']);
        $commit->setUserId('d03fc9f6-3c6b-4428-8d6f-ba07c7c6e856');
        $commit->setTarget($target);

        $commit->getAssets()->add(new Asset());
        $commit->getAssets()->add(new Asset());
        $commit->setToken('a_token');
        $target->setAuthorizationKey($accessToken);
        $target->setTargetUrl('http://localhost/api/v1/upload/enqueue/');
        $target->setAuthorizationScheme('OAuth');
        $commit->setTarget($target);

        $em->expects($this->once())
            ->method('find')
            ->willReturn($commit);

        $handler = new AssetConsumerNotifyHandler(
            $clientStub,
            $em,
            $uploaderUrl
        );

        $message = new AssetConsumerNotify('an_ID');
        $handler($message);
    }
}
