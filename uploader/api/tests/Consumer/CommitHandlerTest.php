<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use App\Consumer\Handler\CommitHandler;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\BulkData;
use App\Entity\BulkDataRepository;
use App\Storage\SubDefinitionManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class CommitHandlerTest extends TestCase
{
    public function testCommit(): void
    {
        $assetRepo = $this->createMock(AssetRepository::class);
        $assetRepo->expects($this->once())
            ->method('attachCommit');
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
                $this->callback(function ($subject) {
                    return $subject instanceof EventMessage
                        && is_string($subject->getPayload()['id'])
                        ;
                })
            );

        $assetManager = $this->createMock(SubDefinitionManager::class);
        $assetManager
            ->expects($this->once())
            ->method('getTotalSize')
            ->willReturn(42);

        $handler = new CommitHandler($producerStub, $assetManager);
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
