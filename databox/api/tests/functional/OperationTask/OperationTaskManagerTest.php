<?php

declare(strict_types=1);

namespace App\Tests\Functional\OperationTask;

use App\Entity\Admin\OperationTask;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\OperationTaskManager;
use App\OperationTask\OperationTaskRegistry;
use App\OperationTask\RunContext;
use App\Tests\Functional\AbstractDataboxTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\MessageBusInterface;

class OperationTaskManagerTest extends AbstractDataboxTestCase
{
    private const string TASK_NAME = 'fake_task';

    /**
     * @param \Closure(RunContext): void $run
     *
     * @return array{OperationTaskManager, \ArrayObject<string, int>}
     */
    private function createManager(\Closure $run): array
    {
        $calls = new \ArrayObject(['started' => 0, 'finished' => 0]);
        $task = new class($run, $calls) implements OperationTaskInterface {
            public function __construct(
                private readonly \Closure $run,
                private readonly \ArrayObject $calls,
            ) {
            }

            public static function getName(): string
            {
                return 'fake_task';
            }

            public function validate(array $payload): void
            {
            }

            public function handle(array $payload, RunContext $context): void
            {
                ++$this->calls['started'];
                ($this->run)($context);
                ++$this->calls['finished'];
            }
        };

        $manager = new OperationTaskManager(
            self::getEntityManager(),
            self::getService(MessageBusInterface::class),
            new OperationTaskRegistry(new ServiceLocator([self::TASK_NAME => static fn () => $task])),
            new NullLogger(),
        );

        return [$manager, $calls];
    }

    private function createTask(int $status = OperationTask::STATUS_PENDING): OperationTask
    {
        $em = self::getEntityManager();
        $task = new OperationTask();
        $task->setOwnerId('custom_owner');
        $task->setTask(self::TASK_NAME);
        $task->setStatus($status);
        $em->persist($task);
        $em->flush();

        return $task;
    }

    /**
     * What the cancel endpoint does, behind the back of the worker's entity manager.
     */
    private function cancelInDatabase(string $taskId): void
    {
        self::getEntityManager()->getConnection()->update('operation_task', [
            'status' => OperationTask::STATUS_CANCELLED,
        ], ['id' => $taskId]);
    }

    private function findTask(string $id): OperationTask
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(OperationTask::class, $id);
    }

    public function testRunsAPendingTask(): void
    {
        [$manager, $calls] = $this->createManager(static function (RunContext $context): void {
            $context->start(2);
            $context->advance(2);
        });
        $task = $this->createTask();

        $manager->handleTask($task);

        $this->assertSame(1, $calls['finished']);
        $task = $this->findTask($task->getId());
        $this->assertSame(OperationTask::STATUS_COMPLETED, $task->getStatus());
        $this->assertNotNull($task->getStartedAt());
        $this->assertSame(100, $task->getProgression());
    }

    public function testATaskCancelledBeforeItStartsIsNotRun(): void
    {
        [$manager, $calls] = $this->createManager(static function (): void {
        });
        $task = $this->createTask();
        $this->cancelInDatabase($task->getId());

        $manager->handleTask($task);

        $this->assertSame(0, $calls['started']);
        $task = $this->findTask($task->getId());
        $this->assertSame(OperationTask::STATUS_CANCELLED, $task->getStatus());
        $this->assertNull($task->getStartedAt());
    }

    public function testATaskCancelledWhileRunningStopsAtItsNextProgressUpdate(): void
    {
        $taskId = null;
        [$manager, $calls] = $this->createManager(function (RunContext $context) use (&$taskId): void {
            $context->start(10);
            $context->advance();
            $this->cancelInDatabase($taskId);
            $context->advance();
        });
        $task = $this->createTask();
        $taskId = $task->getId();

        $manager->handleTask($task);

        $this->assertSame(1, $calls['started']);
        $this->assertSame(0, $calls['finished']);
        $task = $this->findTask($taskId);
        $this->assertSame(OperationTask::STATUS_CANCELLED, $task->getStatus());
        $this->assertSame(1, $task->getProgress());
    }
}
