<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\ESBundle\Message\ESIndex;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\Workflow\Message\JobConsumer;
use App\Consumer\Handler\Asset\AssetExportProcess;
use App\Consumer\Handler\File\ImportFile;
use App\Consumer\Handler\IngestWorkspaceAsset;
use App\Consumer\Handler\Rendition\BuildDynamicRendition;
use App\Consumer\Handler\Search\ESPopulate;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\Handler\Workspace\DeleteWorkspace;
use App\Consumer\WorkerQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Each message goes to the worker queue (transport) of its kind of processing.
 */
final class MessengerRoutingTest extends KernelTestCase
{
    private const array QUEUES = [
        WorkerQueue::P1,
        WorkerQueue::P2,
        WorkerQueue::HEAVY,
        WorkerQueue::HEAVY_LOW,
        WorkerQueue::BULK,
        WorkerQueue::BULK_HEAVY,
        WorkerQueue::BULK_HEAVY_LOW,
        WorkerQueue::TASK,
    ];

    /**
     * @param class-string $class
     */
    #[DataProvider('getMessages')]
    public function testMessageIsSentToItsQueue(string $class, string $expectedQueue): void
    {
        $message = new \ReflectionClass($class)->newInstanceWithoutConstructor();

        $this->assertSame([$expectedQueue], $this->dispatch(new Envelope($message)));
    }

    public static function getMessages(): iterable
    {
        yield 'index' => [ESIndex::class, WorkerQueue::P1];
        yield 'workflow job' => [JobConsumer::class, WorkerQueue::P2];
        yield 'notification' => [SendNotification::class, WorkerQueue::P2];
        yield 'dynamic rendition' => [BuildDynamicRendition::class, WorkerQueue::HEAVY];
        yield 'file import' => [ImportFile::class, WorkerQueue::HEAVY];
        yield 'export' => [AssetExportProcess::class, WorkerQueue::HEAVY];
        yield 'workspace ingestion fan-out' => [IngestWorkspaceAsset::class, WorkerQueue::BULK];
        yield 'populate' => [ESPopulate::class, WorkerQueue::TASK];
        yield 'index all assets' => [IndexAllAssets::class, WorkerQueue::TASK];
        yield 'workspace deletion' => [DeleteWorkspace::class, WorkerQueue::TASK];
    }

    #[DataProvider('getJobQueues')]
    public function testWorkflowJobIsSentToTheQueueOfItsJob(string $queue): void
    {
        $message = new \ReflectionClass(JobConsumer::class)->newInstanceWithoutConstructor();

        $this->assertSame([$queue], $this->dispatch(new Envelope($message, [new TransportNamesStamp([$queue])])));
    }

    public static function getJobQueues(): iterable
    {
        yield [WorkerQueue::HEAVY];
        yield [WorkerQueue::HEAVY_LOW];
        yield [WorkerQueue::BULK];
        yield [WorkerQueue::BULK_HEAVY];
        yield [WorkerQueue::BULK_HEAVY_LOW];
    }

    /**
     * @return string[] the queues the envelope was sent to
     */
    private function dispatch(Envelope $envelope): array
    {
        self::bootKernel();

        /** @var array<string, InMemoryTransport> $queues */
        $queues = [];
        foreach (self::QUEUES as $name) {
            /** @var TestTransport $transport */
            $transport = self::getContainer()->get('messenger.transport.'.$name);
            $queues[$name] = $transport->intercept();
        }

        self::getContainer()->get(MessageBusInterface::class)->dispatch($envelope);

        $sentTo = [];
        foreach ($queues as $name => $queue) {
            foreach ($queue->getSent() as $sent) {
                if ($sent->getMessage() === $envelope->getMessage()) {
                    $sentTo[] = $name;
                }
            }
        }

        return $sentTo;
    }
}
