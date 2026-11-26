<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer\Listener;

use App\Consumer\Listener\TemporaryFilesPurgeListener;
use App\Service\Storage\TemporaryFiles;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Worker;

class TemporaryFilesPurgeListenerTest extends TestCase
{
    public function testStaleFilesArePurged(): void
    {
        $temporaryFiles = $this->createMock(TemporaryFiles::class);
        $temporaryFiles->expects($this->once())
            ->method('purge')
            ->with(TemporaryFilesPurgeListener::MAX_AGE)
            ->willReturn(3);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $logger->expects($this->never())->method('warning');

        new TemporaryFilesPurgeListener($temporaryFiles, $logger)($this->createEvent());
    }

    public function testNothingIsLoggedWithoutStaleFiles(): void
    {
        $temporaryFiles = $this->createMock(TemporaryFiles::class);
        $temporaryFiles->method('purge')->willReturn(0);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        new TemporaryFilesPurgeListener($temporaryFiles, $logger)($this->createEvent());
    }

    public function testFailureDoesNotPreventTheWorkerFromStarting(): void
    {
        $temporaryFiles = $this->createMock(TemporaryFiles::class);
        $temporaryFiles->method('purge')->willThrowException(new \RuntimeException('Permission denied'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        new TemporaryFilesPurgeListener($temporaryFiles, $logger)($this->createEvent());
    }

    private function createEvent(): WorkerStartedEvent
    {
        return new WorkerStartedEvent($this->createStub(Worker::class));
    }
}
