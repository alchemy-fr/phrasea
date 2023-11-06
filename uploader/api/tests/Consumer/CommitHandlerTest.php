<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use App\Consumer\Handler\CommitHandler;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Target;
use App\Entity\TargetParams;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use ColinODell\PsrTestLogger\TestLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class CommitHandlerTest extends TestCase
{
    public function testCommit(): void
    {
        $assetRepo = $this->createMock(AssetRepository::class);
        $assetRepo->expects($this->once())
            ->method('attachCommit');
        $targetParamsRepo = $this->createMock(EntityRepository::class);
        $targetParamsRepo->expects($this->once())
            ->method('findOneBy')
            ->willReturn(new TargetParams());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->will($this->returnValueMap([
                [Asset::class, $assetRepo],
                [TargetParams::class, $targetParamsRepo],
            ]));
        $em->method('getReference')
            ->with(Target::class, '5c7bf71b-d78e-4fef-ab03-cfd0e7142d09')
            ->willReturn(new Target());

        $producerStub = $this->createMock(EventProducer::class);
        $producerStub
            ->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(fn ($subject) => $subject instanceof EventMessage
                    && is_string($subject->getPayload()['id']))
            );

        $assetManager = $this->createMock(AssetManager::class);
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
            'target_id' => '5c7bf71b-d78e-4fef-ab03-cfd0e7142d09',
        ]);
        $handler->handle($message);
    }
}
