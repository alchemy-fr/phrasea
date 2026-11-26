<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\NotifierState;
use App\Consumer\Handler\Asset\NotifyAssetQuarantined;
use App\Consumer\Handler\Asset\NotifyAssetQuarantinedHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\InterceptedTransports;

/**
 * The owner of an asset is told when it enters the quarantine.
 */
class NotifyAssetQuarantinedTest extends AbstractDataboxTestCase
{
    private const string OWNER_ID = KeycloakClientTestMock::USER_UID;

    private Workspace $workspace;
    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        self::getService(NotifierState::class)->setEnabled(true);

        $this->workspace = $this->createWorkspace(['name' => 'Press room']);
        $this->asset = $this->createAsset([
            'workspace' => $this->workspace,
            'name' => 'Suspicious file',
            'ownerId' => self::OWNER_ID,
        ]);
    }

    public function testPuttingAnAssetInQuarantineDispatchesTheNotification(): void
    {
        $this->assertSame(AssetStatusEnum::Accepted, $this->asset->getStatus());

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);

        $this->assertSame([$this->asset->getId()], $this->getDispatchedAssetIds($queue));
    }

    public function testAnAssetCreatedInQuarantineDispatchesTheNotification(): void
    {
        $this->workspace->setAssetDefaultStatus(AssetStatusEnum::Quarantined);
        self::getEntityManager()->flush();

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER_ID,
        ]);

        $this->assertSame(AssetStatusEnum::Quarantined, $asset->getStatus());
        $this->assertSame([$asset->getId()], $this->getDispatchedAssetIds($queue));
    }

    public function testAnUpdateOfAnAlreadyQuarantinedAssetDispatchesNothing(): void
    {
        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);
        $queue->reset();

        $this->asset->setTrackingId('tracked');
        self::getEntityManager()->flush();

        $this->assertSame([], $this->getDispatchedAssetIds($queue));
    }

    public function testNothingIsDispatchedWhileNotificationsAreDisabled(): void
    {
        self::getService(NotifierState::class)->setEnabled(false);

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);

        $this->assertSame([], $this->getDispatchedAssetIds($queue));
    }

    public function testTheHandlerNotifiesTheOwnerOnly(): void
    {
        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);
        $queue->reset();

        self::getService(NotifyAssetQuarantinedHandler::class)(new NotifyAssetQuarantined($this->asset->getId()));

        $notifications = $this->getSentNotifications($queue);
        $this->assertCount(1, $notifications);
        [$userIds, $topic, $params] = $notifications[0];
        $this->assertSame([self::OWNER_ID], $userIds);
        $this->assertSame(Asset::EVENT_QUARANTINED, $topic);
        $this->assertSame('Suspicious file', $params['name']);
        $this->assertSame('/quarantine/'.$this->asset->getId(), $params['url']);
        $this->assertSame($this->workspace->getId(), $params['workspaceId']);
        $this->assertSame('Press room', $params['workspaceName']);
    }

    public function testTheHandlerSendsNothingToAnOwnerWhoIsNoUser(): void
    {
        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->asset->setOwnerId('imported_owner');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);
        $queue->reset();

        self::getService(NotifyAssetQuarantinedHandler::class)(new NotifyAssetQuarantined($this->asset->getId()));

        $this->assertSame([], $this->getSentNotifications($queue));
    }

    public function testTheHandlerSendsNothingOnceTheAssetLeftTheQuarantine(): void
    {
        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        $this->setStatus($this->asset, AssetStatusEnum::Quarantined);
        $this->setStatus($this->asset, AssetStatusEnum::Accepted);
        $queue->reset();

        self::getService(NotifyAssetQuarantinedHandler::class)(new NotifyAssetQuarantined($this->asset->getId()));

        $this->assertSame([], $this->getSentNotifications($queue));
    }

    private function setStatus(Asset $asset, AssetStatusEnum $status): void
    {
        $asset->setStatus($status);
        self::getEntityManager()->flush();
    }

    /**
     * @return string[]
     */
    private function getDispatchedAssetIds(InterceptedTransports $queue): array
    {
        $assetIds = [];
        foreach ($queue->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof NotifyAssetQuarantined) {
                $assetIds[] = $message->getAssetId();
            }
        }

        return $assetIds;
    }

    /**
     * @return array<int, array{string[], string|null, array<string, mixed>}>
     */
    private function getSentNotifications(InterceptedTransports $queue): array
    {
        $notifications = [];
        foreach ($queue->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if (!$message instanceof SendNotification) {
                continue;
            }
            foreach ($message->selectors as $selector) {
                $notifications[] = [$selector->userIds, $selector->topic?->topic, $selector->topic?->params ?? []];
            }
        }

        return $notifications;
    }
}
