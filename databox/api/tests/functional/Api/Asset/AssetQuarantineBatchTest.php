<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Batch resolution of the quarantine: POST /assets/quarantine/prepare-batch
 * and POST /assets/quarantine/batch.
 *
 * The workspace is owned by OTHER_USER_UID (who may bypass the quarantine);
 * USER_UID is a member owning the quarantined assets.
 */
final class AssetQuarantineBatchTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;
    use QuarantineTestTrait;

    private const string UNKNOWN_ID = '7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11';

    public function testPrepareTellsWhichActionsEachAssetIsEligibleTo(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('second-ws', self::OTHER);

        [$alone] = $this->createQuarantinedAsset($workspace);

        [$withDuplicate, $file] = $this->createQuarantinedAsset($workspace);
        $duplicate = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $this->linkDuplicate($file, $duplicate->getSource(), 'checksum');

        [$foreignDuplicate, $file] = $this->createQuarantinedAsset($workspace);
        $foreign = $this->createAssetWithSource($otherWorkspace, ['ownerId' => self::OTHER, 'workspace' => $otherWorkspace]);
        $this->linkDuplicate($file, $foreign->getSource(), 'checksum');

        [$twoDuplicates, $file] = $this->createQuarantinedAsset($workspace);
        $first = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $second = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $this->linkDuplicate($file, $first->getSource(), 'checksum');
        $this->linkDuplicate($file, $second->getSource(), 'doc_unique_id');

        $accepted = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);

        $items = $this->prepare(self::OTHER, [
            $alone->getId(),
            $withDuplicate->getId(),
            $foreignDuplicate->getId(),
            $twoDuplicates->getId(),
            $accepted->getId(),
            self::UNKNOWN_ID,
        ]);
        $this->assertCount(6, $items);

        $item = $items[$alone->getId()];
        $this->assertEligibility(['bypass', 'trash', 'delete'], $item);
        $this->assertSame(['mergeAsVersion' => 'no_duplicate', 'mergeDiscard' => 'no_duplicate'], $item['reasons']);
        $this->assertSame([], $item['duplicates']);

        $item = $items[$withDuplicate->getId()];
        $this->assertEligibility(['bypass', 'trash', 'delete', 'mergeAsVersion', 'mergeDiscard'], $item);
        $this->assertCount(1, $item['duplicates']);
        $this->assertSame($duplicate->getId(), $item['duplicates'][0]['asset']['id']);
        $this->assertSame(['checksum'], $item['duplicates'][0]['analyzers']);
        $this->assertTrue($item['duplicates'][0]['validTarget']);

        // A duplicate in another workspace cannot receive the file as a new version
        $item = $items[$foreignDuplicate->getId()];
        $this->assertEligibility(['bypass', 'trash', 'delete', 'mergeDiscard'], $item);
        $this->assertSame(['mergeAsVersion' => 'no_valid_target'], $item['reasons']);
        $this->assertFalse($item['duplicates'][0]['validTarget']);

        $item = $items[$twoDuplicates->getId()];
        $this->assertEligibility(['bypass', 'trash', 'delete', 'mergeAsVersion', 'mergeDiscard'], $item);
        $this->assertEqualsCanonicalizing(
            [$first->getId(), $second->getId()],
            array_map(fn (array $d): string => $d['asset']['id'], $item['duplicates']),
        );

        $item = $items[$accepted->getId()];
        $this->assertEligibility([], $item);
        $this->assertSame(['not_quarantined'], array_values(array_unique($item['reasons'])));
        $this->assertSame([], $item['duplicates']);

        $item = $items[self::UNKNOWN_ID];
        $this->assertEligibility([], $item);
        $this->assertSame(['not_found'], array_values(array_unique($item['reasons'])));
    }

    public function testPrepareFollowsThePermissionsOfEachAction(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $duplicate = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $this->linkDuplicate($file, $duplicate->getSource(), 'checksum');
        [$unreadable] = $this->createQuarantinedAsset($workspace, ['ownerId' => self::OTHER]);

        // The owner of the asset may delete it, not accept it
        $items = $this->prepare(self::OWNER, [$quarantined->getId(), $unreadable->getId()]);

        $item = $items[$quarantined->getId()];
        $this->assertEligibility(['trash', 'delete', 'mergeDiscard'], $item);
        $this->assertSame(['bypass' => 'forbidden', 'mergeAsVersion' => 'forbidden'], $item['reasons']);
        $this->assertCount(1, $item['duplicates']);

        $item = $items[$unreadable->getId()];
        $this->assertEligibility([], $item);
        $this->assertSame(['forbidden'], array_values(array_unique($item['reasons'])));
        $this->assertSame([], $item['duplicates']);
    }

    public function testBatchBypassSkipsTheAssetsNoLongerQuarantined(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$first] = $this->createQuarantinedAsset($workspace);
        [$acceptedMeanwhile] = $this->createQuarantinedAsset($workspace);

        $asset = $this->reloadAsset($acceptedMeanwhile->getId());
        $asset->setStatus(AssetStatusEnum::Accepted);
        self::getEntityManager()->flush();

        $data = $this->batch(self::OTHER, 'bypass', [
            ['id' => $first->getId()],
            ['id' => $acceptedMeanwhile->getId()],
            ['id' => self::UNKNOWN_ID],
        ]);

        $this->assertSame([
            ['id' => $first->getId(), 'status' => 'processed'],
            ['id' => $acceptedMeanwhile->getId(), 'status' => 'skipped', 'reason' => 'not_quarantined'],
            ['id' => self::UNKNOWN_ID, 'status' => 'skipped', 'reason' => 'not_found'],
        ], $data['results']);
        $this->assertSame([1, 2, 0], [$data['processed'], $data['skipped'], $data['failed']]);
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($first->getId())->getStatus());
    }

    public function testBatchBypassRequiresThePermission(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);

        $data = $this->batch(self::OWNER, 'bypass', [['id' => $quarantined->getId()]]);

        $this->assertSame([['id' => $quarantined->getId(), 'status' => 'skipped', 'reason' => 'forbidden']], $data['results']);
        $this->assertSame(AssetStatusEnum::Quarantined, $this->reloadAsset($quarantined->getId())->getStatus());
    }

    public function testBatchMergeAsVersionRequiresATargetAmongSeveral(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $first = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $second = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $unrelated = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $this->linkDuplicate($file, $first->getSource(), 'checksum');
        $this->linkDuplicate($file, $second->getSource(), 'checksum');

        $data = $this->batch(self::OTHER, 'merge', [['id' => $quarantined->getId()]]);
        $this->assertSame([['id' => $quarantined->getId(), 'status' => 'skipped', 'reason' => 'target_required']], $data['results']);

        $data = $this->batch(self::OTHER, 'merge', [['id' => $quarantined->getId(), 'targetId' => $unrelated->getId()]]);
        $this->assertSame([['id' => $quarantined->getId(), 'status' => 'skipped', 'reason' => 'invalid_target']], $data['results']);
        $this->assertNotNull($this->reloadAsset($quarantined->getId()));

        $data = $this->batch(self::OTHER, 'merge', [['id' => $quarantined->getId(), 'targetId' => $second->getId()]]);
        $this->assertSame([['id' => $quarantined->getId(), 'status' => 'processed']], $data['results']);

        // The incoming file became the new source of the chosen target
        $this->assertNull($this->reloadAsset($quarantined->getId()));
        $this->assertSame($file->getId(), $this->reloadAsset($second->getId())->getSource()->getId());
        $this->assertNotSame($file->getId(), $this->reloadAsset($first->getId())->getSource()->getId());
    }

    public function testBatchMergeAsVersionPicksTheOnlyValidTarget(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('second-ws', self::OTHER);
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $target = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $foreign = $this->createAssetWithSource($otherWorkspace, ['ownerId' => self::OTHER, 'workspace' => $otherWorkspace]);
        $this->linkDuplicate($file, $target->getSource(), 'checksum');
        $this->linkDuplicate($file, $foreign->getSource(), 'checksum');
        [$withoutDuplicate] = $this->createQuarantinedAsset($workspace);

        $data = $this->batch(self::OTHER, 'merge', [
            ['id' => $quarantined->getId()],
            ['id' => $withoutDuplicate->getId()],
        ]);

        $this->assertSame([
            ['id' => $quarantined->getId(), 'status' => 'processed'],
            ['id' => $withoutDuplicate->getId(), 'status' => 'skipped', 'reason' => 'no_duplicate'],
        ], $data['results']);
        $this->assertNull($this->reloadAsset($quarantined->getId()));
        $this->assertSame($file->getId(), $this->reloadAsset($target->getId())->getSource()->getId());
    }

    public function testBatchMergeDiscardingTheIncomingFile(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $first = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $second = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $sourceId = $first->getSource()->getId();
        $this->linkDuplicate($file, $first->getSource(), 'checksum');
        $this->linkDuplicate($file, $second->getSource(), 'checksum');

        // No target to pick: the existing assets are left untouched
        $data = $this->batch(self::OWNER, 'merge', [['id' => $quarantined->getId()]], ['addAsVersion' => false]);

        $this->assertSame([['id' => $quarantined->getId(), 'status' => 'processed']], $data['results']);
        $this->assertNull($this->reloadAsset($quarantined->getId()));
        $this->assertSame($sourceId, $this->reloadAsset($first->getId())->getSource()->getId());
    }

    public function testBatchTrashAndDelete(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$toTrash] = $this->createQuarantinedAsset($workspace);
        [$toDelete] = $this->createQuarantinedAsset($workspace);
        [$notMine] = $this->createQuarantinedAsset($workspace, ['ownerId' => self::OTHER]);

        $data = $this->batch(self::OWNER, 'trash', [['id' => $toTrash->getId()], ['id' => $notMine->getId()]]);
        $this->assertSame([
            ['id' => $toTrash->getId(), 'status' => 'processed'],
            ['id' => $notMine->getId(), 'status' => 'skipped', 'reason' => 'forbidden'],
        ], $data['results']);
        $this->assertNotNull($this->reloadAsset($toTrash->getId())->getDeletedAt());
        $this->assertNull($this->reloadAsset($notMine->getId())->getDeletedAt());

        // A trashed asset is out of the quarantine
        $data = $this->batch(self::OWNER, 'delete', [['id' => $toDelete->getId()], ['id' => $toTrash->getId()]]);
        $this->assertSame([
            ['id' => $toDelete->getId(), 'status' => 'processed'],
            ['id' => $toTrash->getId(), 'status' => 'skipped', 'reason' => 'not_found'],
        ], $data['results']);
        $this->assertNull($this->reloadAsset($toDelete->getId()));
        $this->assertNotNull($this->reloadAsset($toTrash->getId()));
    }

    public function testBatchEndpointsRequireAnAuthenticatedUser(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);

        $this->request('POST', '/assets/quarantine/prepare-batch', null, ['ids' => [$quarantined->getId()]]);
        $this->assertResponseStatusCodeSame(401);

        $this->request('POST', '/assets/quarantine/batch', null, [
            'action' => 'trash',
            'items' => [['id' => $quarantined->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertNull($this->reloadAsset($quarantined->getId())->getDeletedAt());
    }

    public function testBatchesAreLimitedToAHundredAssets(): void
    {
        $this->createQuarantineWorkspace();
        $ids = array_fill(0, 101, self::UNKNOWN_ID);

        $this->request('POST', '/assets/quarantine/prepare-batch', self::OTHER, ['ids' => $ids]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('POST', '/assets/quarantine/batch', self::OTHER, [
            'action' => 'bypass',
            'items' => array_map(fn (string $id): array => ['id' => $id], $ids),
        ]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('POST', '/assets/quarantine/batch', self::OTHER, ['action' => 'bypass', 'items' => []]);
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, array<string, mixed>> the items by asset ID
     */
    private function prepare(string $userId, array $ids): array
    {
        $data = $this->request('POST', '/assets/quarantine/prepare-batch', $userId, ['ids' => $ids])->toArray();
        $this->assertResponseIsSuccessful();

        return array_column($data['items'], null, 'id');
    }

    private function batch(string $userId, string $action, array $items, array $extra = []): array
    {
        $data = $this->request('POST', '/assets/quarantine/batch', $userId, array_merge([
            'action' => $action,
            'items' => $items,
        ], $extra))->toArray();
        $this->assertResponseIsSuccessful();

        return $data;
    }

    /**
     * @param string[] $eligible the actions the item is eligible to, the others must not be
     */
    private function assertEligibility(array $eligible, array $item): void
    {
        foreach (['bypass', 'trash', 'delete', 'mergeAsVersion', 'mergeDiscard'] as $action) {
            $this->assertSame(in_array($action, $eligible, true), $item[$action], sprintf('Eligibility of "%s"', $action));
            $this->assertSame(!in_array($action, $eligible, true), isset($item['reasons'][$action]), sprintf('Reason of "%s"', $action));
        }
    }
}
