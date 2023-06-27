<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class AssetConsumerNotifyHandlerTest extends TestCase
{
    public function testAssetConsumerNotify(): void
    {
        $accessToken = 'secret_token';
        $uploadBaseUrl = 'http://localhost:8080';

        $consumerResponse = new Response(200, [
            'Content-Type' => 'application/json',
        ], '{"meta":{"api_version":"1.4.1","request":"POST \/api\/v1\/upload\/enqueue\/","response_time":"2019-06-05T16:28:24+02:00","http_code":200,"error_type":null,"error_message":null,"error_details":null,"charset":"UTF-8"},"response":{"data":{"assets":["4c097077-a26b-4af4-9a5d-b13fd4c77b3d","a134145e-9461-4f0a-8bd8-7025d31a6b8e"],"publisher":"d03fc9f6-3c6b-4428-8d6f-ba07c7c6e856","token":"a_token"}}}');

        $clientHandler = new MockHandler([
            $consumerResponse,
        ]);
        $clientStub = new Client(['handler' => $clientHandler]);

        $em = $this->createMock(EntityManagerInterface::class);

        $commit = Commit::fromArray([
            'form' => ['foo' => 'bar'],
            'user_id' => 'd03fc9f6-3c6b-4428-8d6f-ba07c7c6e856',
            'target_id' => 'c705d014-5e18-4711-bad6-5e9e27e10099',
        ], $em);
        $commit->getAssets()->add(new Asset());
        $commit->getAssets()->add(new Asset());
        $commit->setToken('a_token');
        $target = new Target();
        $target->setTargetAccessToken($accessToken);
        $target->setTargetUrl('http://localhost/api/v1/upload/enqueue/');
        $target->setTargetTokenType('OAuth');
        $commit->setTarget($target);

        $em->expects($this->once())
            ->method('find')
            ->willReturn($commit);

        $handler = new AssetConsumerNotifyHandler(
            $clientStub,
            $uploadBaseUrl
        );
        $handler->setEntityManager($em);

        $logger = new TestLogger();
        $handler->setLogger($logger);

        $message = new EventMessage($handler::EVENT, [
            'id' => 'an_ID',
        ]);
        $handler->handle($message);

        $this->assertEquals('/api/v1/upload/enqueue/', $clientHandler->getLastRequest()->getUri()->getPath());

        $postBody = json_decode($clientHandler->getLastRequest()->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('assets', $postBody);
        $this->assertArrayHasKey('commit_id', $postBody);
        $this->assertCount(2, $postBody['assets']);
        $this->assertEquals('a_token', $postBody['token']);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $postBody['commit_id']);
        $this->assertEquals('http://localhost:8080', $postBody['base_url']);

        $this->assertEquals(0, $clientHandler->count());
    }
}
