<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use App\Consumer\Handler\CommitHandler;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\BulkData;
use App\Entity\BulkDataRepository;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use GuzzleHttp\Psr7\Response;

class CommitHandlerTest extends TestCase
{
    public function testCommit(): void
    {
        $assetRepo = $this->createMock(AssetRepository::class);
        $assetRepo->expects($this->once())
            ->method('attachFormData');
        $bulkRepo = $this->createMock(BulkDataRepository::class);
        $bulkRepo->expects($this->once())
            ->method('getBulkDataArray')
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->will($this->returnValueMap([
                [Asset::class, $assetRepo],
                [BulkData::class, $bulkRepo],
            ]));

        $producerStub = $this->createMock(EventProducer::class);
        $producerStub
            ->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(function($subject){
                    return $subject instanceof EventMessage
                        && is_array($subject->getPayload()['files'])
                        && !array_key_exists('form', $subject->getPayload())
                        && is_string($subject->getPayload()['user_id']);
                })
            );

        $handler = new CommitHandler($producerStub);
        $handler->setEntityManager($em);

        $logger = new TestLogger();
        $handler->setLogger($logger);

        $message = new EventMessage($handler::EVENT, [
            'files' => [
                '4c097077-a26b-4af4-9a5d-b13fd4c77b3d',
                'a134145e-9461-4f0a-8bd8-7025d31a6b8e',
            ],
            'form' => ['foo' => 'bar'],
            'user_id' => 'd03fc9f6-3c6b-4428-8d6f-ba07c7c6e856',
        ]);
        $handler->handle($message);
    }
}
