<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\Workflow\Message\JobConsumer;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Core\Asset;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Workflow runs: reading one, cancelling it, re-running one of its jobs.
 * Listing is covered by WorkflowStateTest.
 */
final class WorkflowRunControlTest extends AbstractDataboxTestCase
{
    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private function headers(?string $userId): array
    {
        return null === $userId ? [] : [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function getStateRepository(): StateRepositoryInterface
    {
        return self::getContainer()->get(StateRepositoryInterface::class);
    }

    /**
     * An ingest run on an asset of OWNER (OTHER can see the workspace, not
     * edit the asset).
     */
    private function startIngest(): ModelWorkflowState
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::OWNER,
            'public' => true,
        ]);

        return $this->startWorkflow($asset);
    }

    private function startWorkflow(Asset $asset): ModelWorkflowState
    {
        $stateRepository = $this->getStateRepository();
        $state = new ModelWorkflowState(
            $stateRepository,
            'asset-ingest:'.$asset->getWorkspaceId(),
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
        );
        $stateRepository->persistWorkflowState($state);

        return $state;
    }

    private function getRun(string $userId, string $id): array
    {
        $response = static::createClient()->request('GET', '/workflows/'.$id, [
            'headers' => $this->headers($userId),
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray();
    }

    private function getFirstJobId(array $run): string
    {
        return $run['stages'][0]['jobs'][0]['jobId'];
    }

    public function testTheRunDescribesItsPlan(): void
    {
        $state = $this->startIngest();

        $run = $this->getRun(self::OWNER, $state->getId());

        $this->assertSame($state->getId(), $run['id']);
        $this->assertSame(ModelWorkflowState::STATUS_STARTED, $run['status']);
        $this->assertNull($run['endedAt']);
        $this->assertNotEmpty($run['stages']);
        $this->assertNotEmpty($this->getFirstJobId($run));
        $this->assertSame(1, $run['number']);
    }

    public function testAUserWhoCannotEditTheAssetCannotReadTheRun(): void
    {
        $state = $this->startIngest();

        static::createClient()->request('GET', '/workflows/'.$state->getId(), [
            'headers' => $this->headers(self::OTHER),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testARunWithoutAssetIsOnlyReadByAdmins(): void
    {
        $stateRepository = $this->getStateRepository();
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $state = new ModelWorkflowState(
            $stateRepository,
            'asset-ingest:'.$workspace->getId(),
            null,
        );
        $stateRepository->persistWorkflowState($state);

        static::createClient()->request('GET', '/workflows/'.$state->getId(), [
            'headers' => $this->headers(self::OWNER),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $run = $this->getRun(KeycloakClientTestMock::ADMIN_UID, $state->getId());
        $this->assertNull($run['asset']);
        $this->assertNull($run['number']);
    }

    public function testUnknownRunIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/workflows/00000000-0000-4000-8000-000000000000', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('POST', '/workflows/00000000-0000-4000-8000-000000000000/cancel', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('POST', '/workflows/00000000-0000-4000-8000-000000000000/jobs/foo/rerun', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTheAssetEditorCancelsARunningWorkflow(): void
    {
        $state = $this->startIngest();
        $jobId = $this->getFirstJobId($this->getRun(self::OWNER, $state->getId()));
        $running = new JobState($state->getId(), $jobId, JobState::STATUS_RUNNING);
        $this->getStateRepository()->persistJobState($running);

        $response = static::createClient()->request('POST', '/workflows/'.$state->getId().'/cancel', [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(ModelWorkflowState::STATUS_CANCELLED, $response->toArray()['status']);

        $stateRepository = $this->getStateRepository();
        $this->assertSame(ModelWorkflowState::STATUS_CANCELLED, $stateRepository->getWorkflowState($state->getId())->getStatus());
        $this->assertNotNull($stateRepository->getWorkflowState($state->getId())->getCancelledAt());
        // The running jobs are cancelled too
        $this->assertSame(JobState::STATUS_CANCELLED, $stateRepository->getLastJobState($state->getId(), $jobId)->getStatus());
    }

    public function testCancellingAFailedWorkflowChangesNothing(): void
    {
        $state = $this->startIngest();
        $state->setStatus(ModelWorkflowState::STATUS_FAILURE);
        $this->getStateRepository()->persistWorkflowState($state);

        $response = static::createClient()->request('POST', '/workflows/'.$state->getId().'/cancel', [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(ModelWorkflowState::STATUS_FAILURE, $response->toArray()['status']);
    }

    public function testRerunningAFailedJobTriggersItAgain(): void
    {
        $state = $this->startIngest();
        $jobId = $this->getFirstJobId($this->getRun(self::OWNER, $state->getId()));
        $this->getStateRepository()->persistJobState(new JobState($state->getId(), $jobId, JobState::STATUS_FAILURE));

        $client = static::createClient();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $queue = $transport->intercept();

        $response = $client->request('POST', '/workflows/'.$state->getId().'/jobs/'.$jobId.'/rerun', [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(ModelWorkflowState::STATUS_STARTED, $response->toArray()['status']);

        $messages = array_values(array_filter(
            array_map(fn ($envelope) => $envelope->getMessage(), $queue->getSent()),
            fn (object $message): bool => $message instanceof JobConsumer,
        ));
        $this->assertCount(1, $messages);

        self::getEntityManager()->clear();
        $jobStates = $this->getStateRepository()->getJobStates($state->getId(), $jobId);
        $this->assertCount(2, $jobStates);
        $statuses = array_map(fn (JobState $jobState): int => $jobState->getStatus(), $jobStates);
        $this->assertEqualsCanonicalizing([JobState::STATUS_FAILURE, JobState::STATUS_TRIGGERED], $statuses);
        foreach ($jobStates as $jobState) {
            if (JobState::STATUS_TRIGGERED === $jobState->getStatus()) {
                $this->assertTrue($jobState->getInputs()['rerun']);
            }
        }
    }

    public function testRerunningAJobThatNeverRanDoesNothing(): void
    {
        $state = $this->startIngest();
        $jobId = $this->getFirstJobId($this->getRun(self::OWNER, $state->getId()));

        $client = static::createClient();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $queue = $transport->intercept();

        $client->request('POST', '/workflows/'.$state->getId().'/jobs/'.$jobId.'/rerun', [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $queue->getSent());
        self::getEntityManager()->clear();
        $this->assertSame([], $this->getStateRepository()->getJobStates($state->getId(), $jobId));
    }

    public static function getUnauthorizedCallers(): iterable
    {
        yield 'anonymous' => [null, 401];
        yield 'user who cannot edit the asset' => [self::OTHER, 403];
    }

    /**
     * @dataProvider getUnauthorizedCallers
     */
    public function testOnlyTheAssetEditorsCanCancelARun(?string $userId, int $expectedCode): void
    {
        $state = $this->startIngest();

        static::createClient()->request('POST', '/workflows/'.$state->getId().'/cancel', [
            'headers' => $this->headers($userId),
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(ModelWorkflowState::STATUS_STARTED, $this->getStateRepository()->getWorkflowState($state->getId())->getStatus());
    }

    /**
     * @dataProvider getUnauthorizedCallers
     */
    public function testOnlyTheAssetEditorsCanRerunAJob(?string $userId, int $expectedCode): void
    {
        $state = $this->startIngest();
        $jobId = $this->getFirstJobId($this->getRun(self::OWNER, $state->getId()));
        $this->getStateRepository()->persistJobState(new JobState($state->getId(), $jobId, JobState::STATUS_FAILURE));

        static::createClient()->request('POST', '/workflows/'.$state->getId().'/jobs/'.$jobId.'/rerun', [
            'headers' => $this->headers($userId),
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame(JobState::STATUS_FAILURE, $this->getStateRepository()->getLastJobState($state->getId(), $jobId)->getStatus());
    }
}
