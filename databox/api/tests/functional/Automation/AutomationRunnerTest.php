<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Automation\Action\MoveToCollectionAction;
use App\Automation\Action\TagsAction;
use App\Automation\AutomationCursor;
use App\Automation\AutomationRunner;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use App\Repository\Automation\AutomationSeenAssetRepository;
use App\Tests\Functional\AbstractSearchTestCase;
use Symfony\Component\Lock\LockFactory;

final class AutomationRunnerTest extends AbstractSearchTestCase
{
    use AutomationTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;

    private Workspace $workspace;
    private Collection $source;
    private Tag $tag;

    /**
     * @var array<string, string> asset IDs by name
     */
    private array $assetIds = [];

    private function setUpSearch(int $assetCount = 2): SavedSearch
    {
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->source = $this->createCollection([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER,
            'name' => 'Source',
        ]);
        $this->tag = $this->findOrCreateTagByName('Processed', $this->workspace);
        for ($i = 1; $i <= $assetCount; ++$i) {
            $this->createSourceAsset('Asset '.$i);
        }
        // Another asset of the workspace, out of the search
        $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'Out']);
        $this->indexAssets();

        return $this->createSavedSearchWith(self::OWNER, [
            'conditions' => [sprintf('@collection = "%s"', $this->source->getId())],
        ]);
    }

    private function createSourceAsset(string $name): Asset
    {
        $asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER,
            'name' => $name,
            'collectionId' => $this->source->getId(),
        ]);
        $this->assetIds[$asset->getId()] = $name;

        return $asset;
    }

    private function indexAssets(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    private function tagAction(): array
    {
        return ['type' => TagsAction::TYPE, 'config' => ['add' => [$this->tag->getId()], 'remove' => []]];
    }

    /**
     * @return string[] names of the assets holding the tag
     */
    private function getTaggedAssetNames(): array
    {
        $em = self::getEntityManager();
        $em->clear();
        $names = [];
        foreach ($em->getRepository(Asset::class)->findBy(['workspace' => $this->workspace->getId()]) as $asset) {
            foreach ($asset->getTags() as $tag) {
                if ($tag->getId() === $this->tag->getId()) {
                    $names[] = $this->assetIds[$asset->getId()] ?? $asset->getId();
                }
            }
        }
        sort($names);

        return $names;
    }

    private function countSeen(string $automationId): int
    {
        return self::getService(AutomationSeenAssetRepository::class)->countSeen($automationId);
    }

    public function testRunAllThenNew(): void
    {
        $savedSearch = $this->setUpSearch();
        $automation = $this->createAutomationEntity($savedSearch, [$this->tagAction()], AutomationScopeEnum::All);
        $id = $automation->getId();

        $run = $this->runAutomation($id);
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus(), (string) $run->getError());
        $this->assertSame(2, $run->getMatchedCount());
        $this->assertSame(2, $run->getNewCount());
        $this->assertSame(2, $run->getProcessedCount());
        $this->assertSame(0, $run->getFailedCount());
        $this->assertNotNull($run->getStartedAt());
        $this->assertNotNull($run->getEndedAt());
        $this->assertSame([[
            'type' => TagsAction::TYPE,
            'status' => 'success',
            'processed' => 2,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ]], $run->getActionResults());
        $this->assertSame(['Asset 1', 'Asset 2'], $this->getTaggedAssetNames());
        $this->assertSame(2, $this->countSeen($id));

        $automation = $this->findAutomation($id);
        $this->assertSame(AutomationRunStatusEnum::Success, $automation->getLastRunStatus());
        $this->assertNotNull($automation->getLastRunAt());

        // Scope "all": every result again
        $run = $this->runAutomation($id);
        $this->assertSame(2, $run->getMatchedCount());
        $this->assertSame(0, $run->getNewCount());
        $this->assertSame(2, $run->getProcessedCount());

        // Scope "new": nothing new
        $automation = $this->findAutomation($id);
        $automation->setScope(AutomationScopeEnum::New);
        self::getEntityManager()->flush();
        $run = $this->runAutomation($id);
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus());
        $this->assertSame(2, $run->getMatchedCount());
        $this->assertSame(0, $run->getNewCount());
        $this->assertSame(0, $run->getProcessedCount());

        // An asset entering the search is new
        $this->workspace = self::getEntityManager()->find(Workspace::class, $this->workspace->getId());
        $this->createSourceAsset('Asset 3');
        $this->indexAssets();
        $run = $this->runAutomation($id);
        $this->assertSame(3, $run->getMatchedCount());
        $this->assertSame(1, $run->getNewCount());
        $this->assertSame(1, $run->getProcessedCount());
        $this->assertSame(['Asset 1', 'Asset 2', 'Asset 3'], $this->getTaggedAssetNames());
        $this->assertSame(3, $this->countSeen($id));
    }

    public function testDryRunDoesNotTouchTheCursor(): void
    {
        $savedSearch = $this->setUpSearch();
        $id = $this->createAutomationEntity($savedSearch, [$this->tagAction()])->getId();

        $run = $this->runAutomation($id, dryRun: true);
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus(), (string) $run->getError());
        $this->assertTrue($run->isDryRun());
        $this->assertSame(2, $run->getMatchedCount());
        $this->assertSame(2, $run->getNewCount());
        $this->assertSame(0, $run->getProcessedCount());
        $this->assertSame([], $this->getTaggedAssetNames());
        $this->assertSame(0, $this->countSeen($id));
        // A dry run is not the last run of the automation
        $this->assertNull($this->findAutomation($id)->getLastRunStatus());
    }

    public function testFailedAssetsStayNew(): void
    {
        $savedSearch = $this->setUpSearch();
        $id = $this->createAutomationEntity($savedSearch, [
            $this->tagAction(),
            // The collection does not exist (any more): every asset fails
            ['type' => MoveToCollectionAction::TYPE, 'config' => ['collectionId' => '00000000-0000-4000-8000-000000000000']],
        ])->getId();

        $run = $this->runAutomation($id);
        $this->assertSame(AutomationRunStatusEnum::Partial, $run->getStatus(), (string) $run->getError());
        $this->assertSame(2, $run->getNewCount());
        $this->assertSame(0, $run->getProcessedCount());
        $this->assertSame(2, $run->getFailedCount());
        $this->assertSame('success', $run->getActionResults()[0]['status']);
        $this->assertSame('partial', $run->getActionResults()[1]['status']);
        $this->assertSame(2, $run->getActionResults()[1]['failed']);
        $this->assertSame('Destination collection not found', $run->getActionResults()[1]['errors'][0]['message']);
        $this->assertSame(0, $this->countSeen($id));

        // Retried at the next run
        $run = $this->runAutomation($id);
        $this->assertSame(2, $run->getNewCount());
        $this->assertSame(2, $run->getFailedCount());
    }

    public function testCursorReset(): void
    {
        $savedSearch = $this->setUpSearch();
        $automation = $this->createAutomationEntity($savedSearch, [$this->tagAction()]);
        $id = $automation->getId();

        // "Ignore the existing assets"
        $cursor = self::getService(AutomationCursor::class);
        $this->assertSame(2, $cursor->markCurrentResultsAsSeen($this->findAutomation($id)));
        $this->assertSame(2, $this->countSeen($id));
        $run = $this->runAutomation($id);
        $this->assertSame(0, $run->getNewCount());
        $this->assertSame([], $this->getTaggedAssetNames());

        $cursor->empty($this->findAutomation($id));
        $this->assertSame(0, $this->countSeen($id));
        $run = $this->runAutomation($id);
        $this->assertSame(2, $run->getProcessedCount());
    }

    public function testUnavailableOwnerFailsAndDisables(): void
    {
        $savedSearch = $this->createSavedSearchWith(KeycloakClientTestMock::DISABLED_USER_UID, ['query' => 'whatever']);
        $id = $this->createAutomationEntity($savedSearch, [['type' => TagsAction::TYPE, 'config' => ['add' => [], 'remove' => []]]], options: [
            'schedule' => '0 * * * *',
            'nextRunAt' => new \DateTimeImmutable('+1 hour'),
        ])->getId();
        // The notification to the owner is not sent synchronously
        $p2 = self::interceptTransport('p2');

        $run = $this->runAutomation($id, trigger: AutomationTriggerEnum::Schedule);
        $this->assertSame(AutomationRunStatusEnum::Failed, $run->getStatus());
        $this->assertStringContainsString('not available', (string) $run->getError());

        $automation = $this->findAutomation($id);
        $this->assertFalse($automation->isEnabled());
        $this->assertNull($automation->getNextRunAt());
        $this->assertSame(AutomationRunStatusEnum::Failed, $automation->getLastRunStatus());
        $this->assertNotEmpty($p2->getSent());
    }

    public function testConsecutiveScheduledFailuresDisable(): void
    {
        $savedSearch = $this->createSavedSearchWith(self::OWNER, ['query' => 'whatever']);
        $id = $this->createAutomationEntity($savedSearch, [['type' => 'removed_action_type', 'config' => []]], options: [
            'schedule' => '0 * * * *',
            'nextRunAt' => new \DateTimeImmutable('+1 hour'),
            'consecutiveFailures' => SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES - 2,
        ])->getId();
        self::interceptTransport('p2');

        // A manual failure does not count
        $this->assertSame(AutomationRunStatusEnum::Failed, $this->runAutomation($id)->getStatus());
        $this->assertSame(SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES - 2, $this->findAutomation($id)->getConsecutiveFailures());

        $this->runAutomation($id, trigger: AutomationTriggerEnum::Schedule);
        $automation = $this->findAutomation($id);
        $this->assertTrue($automation->isEnabled());
        $this->assertSame(SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES - 1, $automation->getConsecutiveFailures());

        $run = $this->runAutomation($id, trigger: AutomationTriggerEnum::Schedule);
        $this->assertSame(AutomationRunStatusEnum::Failed, $run->getStatus());
        $this->assertStringContainsString('removed_action_type', (string) $run->getError());
        $automation = $this->findAutomation($id);
        $this->assertFalse($automation->isEnabled());
        $this->assertSame(SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES, $automation->getConsecutiveFailures());
    }

    public function testConcurrentRunIsSkipped(): void
    {
        $savedSearch = $this->createSavedSearchWith(self::OWNER, ['query' => 'whatever']);
        $id = $this->createAutomationEntity($savedSearch, [['type' => TagsAction::TYPE, 'config' => ['add' => [], 'remove' => []]]])->getId();

        $lock = self::getService(LockFactory::class)->createLock(AutomationRunner::getLockKey($id));
        $this->assertTrue($lock->acquire());
        try {
            $run = $this->runAutomation($id);
        } finally {
            $lock->release();
        }

        $this->assertSame(AutomationRunStatusEnum::Skipped, $run->getStatus());
        $this->assertNotNull($run->getEndedAt());
        // Not the last run of the automation
        $this->assertNull($this->findAutomation($id)->getLastRunStatus());

        $this->assertSame(AutomationRunStatusEnum::Success, $this->runAutomation($id)->getStatus());
    }
}
