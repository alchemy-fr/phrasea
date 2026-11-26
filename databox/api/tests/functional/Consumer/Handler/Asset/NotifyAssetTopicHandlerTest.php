<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\NotifierState;
use App\Consumer\Handler\Asset\NotifyAssetTopic;
use App\Consumer\Handler\Asset\NotifyAssetTopicHandler;
use App\Consumer\Handler\Collection\NotifyCollectionTopic;
use App\Consumer\Handler\Collection\NotifyCollectionTopicHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\InterceptedTransports;

/**
 * The subscribers from whom an attribute filter rule hides an asset do not get its name.
 */
class NotifyAssetTopicHandlerTest extends AbstractDataboxTestCase
{
    private Asset $asset;
    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        self::getService(NotifierState::class)->setEnabled(true);

        $workspace = $this->createWorkspace(['public' => true]);
        $category = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);
        $this->collection = $this->createCollection(['workspace' => $workspace, 'public' => true]);
        $this->asset = $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Secret plan',
            'public' => true,
            'collectionId' => $this->collection->getId(),
            'attributes' => [['definition' => $category, 'value' => 'internal']],
        ]);
        self::getAttributeFilterManager()->saveRule($workspace, [KeycloakClientTestMock::OTHER_USER_UID], [], 'category = "press"');
    }

    public function testAnAssetUpdateIsOnlySentToTheSubscribersWhoCanReadIt(): void
    {
        $subscriptions = self::getService(SubscriptionManager::class);
        foreach ([KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::OTHER_USER_UID] as $userId) {
            $subscriptions->subscribe($userId, new NotifySelectorDto(Asset::EVENT_UPDATE, Collection::OBJECT_TYPE, $this->collection->getId()));
            $subscriptions->subscribe($userId, new NotifySelectorDto(Asset::EVENT_UPDATE, Asset::OBJECT_TYPE, $this->asset->getId()));
        }

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        self::getService(NotifyAssetTopicHandler::class)(new NotifyAssetTopic(Asset::EVENT_UPDATE, $this->asset->getId(), KeycloakClientTestMock::ADMIN_UID));

        $this->assertSame([
            [[KeycloakClientTestMock::USER_UID], 'Secret plan'],
            [[KeycloakClientTestMock::USER_UID], 'Secret plan'],
        ], $this->getRecipients($queue, 'name'));
    }

    public function testAnAssetAddedToACollectionIsOnlySentToTheSubscribersWhoCanReadIt(): void
    {
        $subscriptions = self::getService(SubscriptionManager::class);
        foreach ([KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::OTHER_USER_UID] as $userId) {
            $subscriptions->subscribe($userId, new NotifySelectorDto(Collection::EVENT_ASSET_ADD, Collection::OBJECT_TYPE, $this->collection->getId()));
        }

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        self::getService(NotifyCollectionTopicHandler::class)(new NotifyCollectionTopic(
            Collection::EVENT_ASSET_ADD,
            $this->collection->getId(),
            KeycloakClientTestMock::ADMIN_UID,
            $this->asset->getId(),
        ));

        $this->assertSame([
            [[KeycloakClientTestMock::USER_UID], 'Secret plan'],
        ], $this->getRecipients($queue, 'assetName'));
    }

    public function testNoNotificationWhenNoSubscriberCanReadTheAsset(): void
    {
        self::getService(SubscriptionManager::class)->subscribe(
            KeycloakClientTestMock::OTHER_USER_UID,
            new NotifySelectorDto(Collection::EVENT_ASSET_ADD, Collection::OBJECT_TYPE, $this->collection->getId()),
        );

        $queue = InterceptedTransports::intercept(self::getContainer(), 'p2');
        self::getService(NotifyCollectionTopicHandler::class)(new NotifyCollectionTopic(
            Collection::EVENT_ASSET_ADD,
            $this->collection->getId(),
            KeycloakClientTestMock::ADMIN_UID,
            $this->asset->getId(),
        ));

        $this->assertSame([], $this->getRecipients($queue, 'assetName'));
    }

    /**
     * @return array<int, array{string[], mixed}> the users of each selector sent, with the asset name they get
     */
    private function getRecipients(InterceptedTransports $queue, string $nameParam): array
    {
        $recipients = [];
        foreach ($queue->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if (!$message instanceof SendNotification) {
                continue;
            }
            foreach ($message->selectors as $selector) {
                // Subscribers are resolved here: none left to the notifier
                $this->assertNull($selector->event);
                $recipients[] = [$selector->userIds, $selector->topic?->params[$nameParam] ?? null];
            }
        }

        return $recipients;
    }
}
