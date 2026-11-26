<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\InterceptedTransports;

/**
 * An access revoked on a workspace is reindexed on the heavy queue: on task, it would wait for the
 * end of a populate, while the revoked user still lists the assets (filtered in Elasticsearch).
 */
class WorkspaceReindexQueueTest extends AbstractDataboxTestCase
{
    public function testRevokingAWorkspaceAccessReindexesItsAssetsOnTheHeavyQueue(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $this->grantUserOnObject(KeycloakClientTestMock::OTHER_USER_UID, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);

        $client = static::createClient();
        $heavy = InterceptedTransports::intercept($client->getContainer(), 'heavy');
        $task = InterceptedTransports::intercept($client->getContainer(), 'task');

        $client->request('DELETE', '/permissions/ace', [
            'json' => [
                'objectType' => Workspace::OBJECT_TYPE,
                'objectId' => $workspace->getId(),
                'userType' => 'user',
                'userId' => KeycloakClientTestMock::OTHER_USER_UID,
            ],
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame([$workspace->getId()], $this->getIndexedWorkspaceIds($heavy));
        $this->assertSame([], $this->getIndexedWorkspaceIds($task));
    }

    /**
     * @return array<int, string|null>
     */
    private function getIndexedWorkspaceIds(InterceptedTransports $queue): array
    {
        $ids = [];
        foreach ($queue->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof IndexAllAssets) {
                $ids[] = $message->workspaceId;
            }
        }

        return $ids;
    }
}
