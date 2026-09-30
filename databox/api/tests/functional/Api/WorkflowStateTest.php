<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Core\Asset;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Service\Workflow\Event\AttributeUpdateWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;

class WorkflowStateTest extends AbstractDataboxTestCase
{
    private function headers(string $userId): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function startWorkflow(Asset $asset, string $rootName): string
    {
        /** @var StateRepositoryInterface $stateRepository */
        $stateRepository = self::getContainer()->get(StateRepositoryInterface::class);
        $event = 'asset-ingest' === $rootName
            ? AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId())
            : AttributeUpdateWorkflowEvent::createEvent([], $asset->getId(), $asset->getWorkspaceId());

        $state = new ModelWorkflowState(
            $stateRepository,
            $rootName.':'.$asset->getWorkspaceId(),
            $event,
        );
        $stateRepository->persistWorkflowState($state);

        return $state->getId();
    }

    public function testRunsAreNumberedAndListedToWhoCanEditTheAsset(): void
    {
        $asset = $this->createAsset(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $otherAsset = $this->createAsset();
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $asset->getWorkspaceId());

        $ingest1 = $this->startWorkflow($asset, 'asset-ingest');
        $update1 = $this->startWorkflow($asset, 'attributes-update');
        $ingest2 = $this->startWorkflow($asset, 'asset-ingest');
        $otherIngest1 = $this->startWorkflow($otherAsset, 'asset-ingest');

        $client = static::createClient();

        // Admins list every run (the runs above started within the same second:
        // their order is not checked)
        $response = $client->request('GET', '/workflows', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $numbers = [];
        foreach ($response->toArray()['hydra:member'] as $item) {
            $numbers[$item['id']] = $item['number'];
        }
        $expected = [
            $otherIngest1 => 1,
            $ingest2 => 2,
            $update1 => 1,
            $ingest1 => 1,
        ];
        ksort($expected);
        ksort($numbers);
        $this->assertSame($expected, $numbers);

        // The others must filter on an asset they can edit
        $response = $client->request('GET', '/workflows', [
            'headers' => $this->headers(KeycloakClientTestMock::USER_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $response->toArray()['hydra:member']);

        $response = $client->request('GET', '/workflows', [
            'query' => ['asset' => '/assets/'.$otherAsset->getId()],
            'headers' => $this->headers(KeycloakClientTestMock::USER_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $response->toArray()['hydra:member']);

        $response = $client->request('GET', '/workflows', [
            'query' => ['asset' => '/assets/'.$asset->getId()],
            'headers' => $this->headers(KeycloakClientTestMock::USER_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $items = $response->toArray()['hydra:member'];
        $this->assertEqualsCanonicalizing([$ingest2, $update1, $ingest1], array_column($items, 'id'));
        $item = $items[array_search($ingest2, array_column($items, 'id'), true)];
        $this->assertSame('asset-ingest:'.$asset->getWorkspaceId(), $item['name']);
        $this->assertSame($asset->getId(), $item['assetId']);
        $this->assertSame('asset_ingest', $item['eventName']);
        $this->assertArrayNotHasKey('state', $item);

        // The run itself tells its number and asset
        $response = $client->request('GET', '/workflows/'.$ingest2, [
            'headers' => $this->headers(KeycloakClientTestMock::USER_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['number']);
        $this->assertSame($asset->getId(), $data['asset']['id']);
    }
}
