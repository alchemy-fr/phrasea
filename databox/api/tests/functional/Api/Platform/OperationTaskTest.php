<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\RunOperationTask;
use App\Entity\Admin\OperationTask;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Admin operation tasks (/operation-tasks): long running maintenance jobs
 * (reindexation, attribute migrations…) run by the "task" queue.
 */
final class OperationTaskTest extends AbstractDataboxTestCase
{
    private function headers(?string $userId): array
    {
        return null === $userId ? [] : [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function createTask(string $name, array $payload = [], int $status = OperationTask::STATUS_PENDING, string $createdAt = 'now'): OperationTask
    {
        $em = self::getEntityManager();
        $task = new OperationTask();
        $task->setTask($name);
        $task->setPayload($payload);
        $task->setStatus($status);
        $task->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $em->persist($task);
        $em->flush();

        // Pin the creation date (the listing is sorted on it)
        $em->getConnection()->update('operation_task', [
            'created_at' => new \DateTimeImmutable($createdAt)->format('Y-m-d H:i:s'),
        ], ['id' => $task->getId()]);

        return $task;
    }

    public static function getForbiddenCalls(): iterable
    {
        yield 'anonymous list' => [null, 'GET', '/operation-tasks', 401];
        yield 'anonymous create' => [null, 'POST', '/operation-tasks', 401];
        yield 'user list' => [KeycloakClientTestMock::USER_UID, 'GET', '/operation-tasks', 403];
        yield 'user create' => [KeycloakClientTestMock::USER_UID, 'POST', '/operation-tasks', 403];
    }

    #[DataProvider('getForbiddenCalls')]
    public function testOperationTasksAreReservedToAdmins(?string $userId, string $method, string $uri, int $expectedCode): void
    {
        static::createClient()->request($method, $uri, [
            'headers' => $this->headers($userId),
            ...('POST' === $method ? ['json' => ['task' => 'index_assets']] : []),
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(0, self::getEntityManager()->getRepository(OperationTask::class)->count([]));
    }

    public function testAUserCannotReadATask(): void
    {
        $task = $this->createTask('index_assets');

        static::createClient()->request('GET', '/operation-tasks/'.$task->getId(), [
            'headers' => $this->headers(KeycloakClientTestMock::USER_UID),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminsListTheTasksMostRecentFirst(): void
    {
        $old = $this->createTask('index_assets', createdAt: '-2 days');
        $recent = $this->createTask('recompute_initial_values', ['definitionId' => 'x'], OperationTask::STATUS_COMPLETED, '-1 hour');

        $response = static::createClient()->request('GET', '/operation-tasks', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
        $this->assertSame([$recent->getId(), $old->getId()], array_column($data['member'], 'id'));
        $this->assertSame('recompute_initial_values', $data['member'][0]['task']);
        $this->assertSame(OperationTask::STATUS_COMPLETED, $data['member'][0]['status']);
        $this->assertSame(['definitionId' => 'x'], $data['member'][0]['payload']);
    }

    public function testAdminsReadATask(): void
    {
        $task = $this->createTask('index_assets', ['workspaceId' => 'ws']);

        $response = static::createClient()->request('GET', '/operation-tasks/'.$task->getId(), [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('index_assets', $data['task']);
        $this->assertSame(OperationTask::STATUS_PENDING, $data['status']);
        $this->assertSame(['workspaceId' => 'ws'], $data['payload']);
    }

    public function testUnknownTaskIs404(): void
    {
        static::createClient()->request('GET', '/operation-tasks/00000000-0000-4000-8000-000000000000', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreatingATaskQueuesItsRun(): void
    {
        $client = static::createClient();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.task');
        $queue = $transport->intercept();

        $response = $client->request('POST', '/operation-tasks', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => [
                'task' => 'index_assets',
                'payload' => ['workspaceId' => 'some-workspace'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('index_assets', $data['task']);
        $this->assertSame(OperationTask::STATUS_PENDING, $data['status']);

        $task = self::getEntityManager()->find(OperationTask::class, $data['id']);
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $task->getOwnerId());
        $this->assertSame(['workspaceId' => 'some-workspace'], $task->getPayload());

        $messages = array_map(fn ($envelope) => $envelope->getMessage(), $queue->getSent());
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(RunOperationTask::class, $messages[0]);
        $this->assertSame($data['id'], $messages[0]->id);
    }

    public function testTheTaskNameIsRequired(): void
    {
        $response = static::createClient()->request('POST', '/operation-tasks', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => ['payload' => []],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('task', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testThePayloadIsValidatedByTheTask(): void
    {
        $response = static::createClient()->request('POST', '/operation-tasks', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => [
                'task' => 'switch_attribute_locales',
                'payload' => ['definitionId' => 'def', 'fromLocale' => 'fr'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('toLocale is required', $response->toArray(false)['description']);
        $this->assertSame(0, self::getEntityManager()->getRepository(OperationTask::class)->count([]));
    }

    public function testAnUnknownTaskIsRejected(): void
    {
        $this->markTestIncomplete('BUG: an unknown task name makes OperationTaskRegistry::getTask() throw an \InvalidArgumentException: POST /operation-tasks answers 500 instead of 400/422 (src/OperationTask/OperationTaskRegistry.php:19).');

        static::createClient()->request('POST', '/operation-tasks', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => ['task' => 'format_hard_drive'],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(0, self::getEntityManager()->getRepository(OperationTask::class)->count([]));
    }

    public static function getCancellableStatuses(): iterable
    {
        yield 'pending' => [OperationTask::STATUS_PENDING];
        yield 'in progress' => [OperationTask::STATUS_IN_PROGRESS];
    }

    public static function getTerminatedStatuses(): iterable
    {
        yield 'completed' => [OperationTask::STATUS_COMPLETED];
        yield 'failed' => [OperationTask::STATUS_FAILED];
        yield 'cancelled' => [OperationTask::STATUS_CANCELLED];
    }

    private function cancel(string $id, ?string $userId = KeycloakClientTestMock::ADMIN_UID): array
    {
        return static::createClient()->request('POST', '/operation-tasks/'.$id.'/cancel', [
            'headers' => $this->headers($userId),
            'json' => [],
        ])->toArray(false);
    }

    private function delete(string $id, ?string $userId = KeycloakClientTestMock::ADMIN_UID): void
    {
        static::createClient()->request('DELETE', '/operation-tasks/'.$id, [
            'headers' => $this->headers($userId),
        ]);
    }

    private function findTask(string $id): ?OperationTask
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(OperationTask::class, $id);
    }

    #[DataProvider('getCancellableStatuses')]
    public function testAdminsCancelAPendingOrRunningTask(int $status): void
    {
        $task = $this->createTask('index_assets', status: $status);

        $data = $this->cancel($task->getId());

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(OperationTask::STATUS_CANCELLED, $data['status']);
        $this->assertNotEmpty($data['endedAt']);
        $this->assertArrayNotHasKey('cancellable', $data);
        $this->assertArrayNotHasKey('terminated', $data);

        $task = $this->findTask($task->getId());
        $this->assertSame(OperationTask::STATUS_CANCELLED, $task->getStatus());
        $this->assertNotNull($task->getEndedAt());
    }

    #[DataProvider('getTerminatedStatuses')]
    public function testATerminatedTaskCannotBeCancelled(int $status): void
    {
        $task = $this->createTask('index_assets', status: $status);

        $this->cancel($task->getId());

        $this->assertResponseStatusCodeSame(409);
        $task = $this->findTask($task->getId());
        $this->assertSame($status, $task->getStatus());
        $this->assertNull($task->getEndedAt());
    }

    public function testCancellingAnUnknownTaskIs404(): void
    {
        $this->cancel('00000000-0000-4000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('getTerminatedStatuses')]
    public function testAdminsDeleteATerminatedTask(int $status): void
    {
        $task = $this->createTask('index_assets', status: $status);

        $this->delete($task->getId());

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findTask($task->getId()));
    }

    #[DataProvider('getCancellableStatuses')]
    public function testAPendingOrRunningTaskCannotBeDeleted(int $status): void
    {
        $task = $this->createTask('index_assets', status: $status);

        $this->delete($task->getId());

        $this->assertResponseStatusCodeSame(409);
        $this->assertNotNull($this->findTask($task->getId()));
    }

    public static function getForbiddenTaskActions(): iterable
    {
        yield 'anonymous cancel' => [null, 'cancel', OperationTask::STATUS_IN_PROGRESS, 401];
        yield 'user cancel' => [KeycloakClientTestMock::USER_UID, 'cancel', OperationTask::STATUS_IN_PROGRESS, 403];
        yield 'anonymous delete' => [null, 'delete', OperationTask::STATUS_COMPLETED, 401];
        yield 'user delete' => [KeycloakClientTestMock::USER_UID, 'delete', OperationTask::STATUS_COMPLETED, 403];
    }

    #[DataProvider('getForbiddenTaskActions')]
    public function testCancellingAndDeletingAreReservedToAdmins(?string $userId, string $action, int $status, int $expectedCode): void
    {
        $task = $this->createTask('index_assets', status: $status);

        'cancel' === $action ? $this->cancel($task->getId(), $userId) : $this->delete($task->getId(), $userId);

        $this->assertResponseStatusCodeSame($expectedCode);
        $task = $this->findTask($task->getId());
        $this->assertNotNull($task);
        $this->assertSame($status, $task->getStatus());
    }
}
