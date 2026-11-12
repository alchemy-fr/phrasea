<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer;

use App\Consumer\Handler\CommitHandler;
use App\Consumer\Handler\CommitMessage;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CommitHandlerTest extends TestCase
{
    public function testCommit(): void
    {
        $assetRepo = $this->createMock(AssetRepository::class);
        $assetRepo->expects($this->once())
            ->method('attachCommit');
        $target = new Target();
        $target->setData(['origin' => 'target', 'source' => 'uploader']);

        /** @var Commit|null $persisted */
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->willReturnMap([
                [Asset::class, $assetRepo],
            ]);
        $em->method('getReference')
            ->with(Target::class, '5c7bf71b-d78e-4fef-ab03-cfd0e7142d09')
            ->willReturn($target);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $commit) use (&$persisted): void {
                $persisted = $commit;
            });

        $busStub = $this->createMock(MessageBusInterface::class);
        $busStub
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) {
                return new Envelope($message, []);
            })
        ;

        $assetManager = $this->createMock(AssetManager::class);
        $assetManager
            ->expects($this->once())
            ->method('getTotalSize')
            ->willReturn(42);

        $handler = new CommitHandler(
            $busStub,
            $assetManager,
            $em
        );
        $message = new CommitMessage(
            '5c7bf71b-d78e-4fef-ab03-cfd0e7142d09',
            'd03fc9f6-3c6b-4428-8d6f-ba07c7c6e856',
            [
                '4c097077-a26b-4af4-9a5d-b13fd4c77b3d',
                'a134145e-9461-4f0a-8bd8-7025d31a6b8e',
            ],
            ['foo' => 'bar', 'origin' => 'user'],
            false,
            'fr',
        );

        $handler($message);

        $this->assertInstanceOf(Commit::class, $persisted);
        // The data of the target wins over the values entered by the user
        $this->assertSame(['foo' => 'bar', 'origin' => 'target', 'source' => 'uploader'], $persisted->getFormData());
        $this->assertSame('fr', $persisted->getFormLocale());
    }
}
