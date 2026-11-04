<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\AttributeValidator;
use App\Attribute\BatchAttributeManager;
use App\Automation\AutomationBatch;
use App\Entity\Core\Tag;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds and removes tags on the assets, through the batch attribute update.
 * A tag only applies to the assets of its workspace.
 */
#[AsTaggedItem(index: self::TYPE)]
final class TagsAction extends AbstractAutomationAction
{
    public const string TYPE = 'tags';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $add = self::getIdList($config, 'add');
        $remove = self::getIdList($config, 'remove');
        if (empty($add) && empty($remove)) {
            self::throwViolations(['add' => 'Choose at least one tag to add or to remove.']);
        }
        if (!empty(array_intersect($add, $remove))) {
            self::throwViolations(['remove' => 'A tag cannot be both added and removed.']);
        }

        foreach (['add' => $add, 'remove' => $remove] as $key => $ids) {
            foreach ($ids as $id) {
                $tag = Uuid::isValid($id) ? $this->em->find(Tag::class, $id) : null;
                if (!$tag instanceof Tag || !$this->isGranted(AbstractVoter::READ, $tag)) {
                    self::throwViolations([$key => sprintf('Tag "%s" not found.', $id)]);
                }
            }
        }

        return [
            'add' => $add,
            'remove' => $remove,
        ];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        /** @var Tag[] $tags */
        $tags = $this->em->getRepository(Tag::class)->findBy([
            'id' => array_merge($batch->config['add'], $batch->config['remove']),
        ]);
        $tagWorkspaces = [];
        foreach ($tags as $tag) {
            $tagWorkspaces[$tag->getId()] = $tag->getWorkspaceId();
        }

        $user = $this->getStrictUser();
        foreach ($batch->groupByWorkspace() as $workspaceId => $assets) {
            $inWorkspace = fn (string $tagId): bool => ($tagWorkspaces[$tagId] ?? null) === $workspaceId;
            $add = array_values(array_filter($batch->config['add'], $inWorkspace));
            $remove = array_values(array_filter($batch->config['remove'], $inWorkspace));
            if (empty($add) && empty($remove)) {
                foreach ($assets as $asset) {
                    $batch->state->markSkipped($asset, 'No tag of the asset workspace');
                }
                continue;
            }

            $assetIds = [];
            foreach ($assets as $asset) {
                if (!$this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset)) {
                    $batch->state->markSkipped($asset, 'Not allowed to edit the tags of the asset');
                    continue;
                }
                $assetIds[] = $asset->getId();
            }
            if (empty($assetIds)) {
                continue;
            }

            $input = new AssetAttributeBatchUpdateInput();
            $input->actions = [];
            foreach ($add as $tagId) {
                $action = new AttributeActionInput();
                $action->definitionId = AttributeValidator::TAGS;
                $action->action = BatchAttributeManager::ACTION_ADD;
                $action->value = $tagId;
                $input->actions[] = $action;
            }
            if (!empty($remove)) {
                $action = new AttributeActionInput();
                $action->definitionId = AttributeValidator::TAGS;
                $action->action = BatchAttributeManager::ACTION_DELETE;
                $action->ids = $remove;
                $input->actions[] = $action;
            }

            try {
                $this->batchAttributeManager->validate($workspaceId, $assetIds, $input);
                $this->batchAttributeManager->handleBatch($workspaceId, $assetIds, $input, $user, true);
            } catch (\Throwable $e) {
                foreach ($assetIds as $assetId) {
                    $batch->state->markFailed($assetId, $e->getMessage());
                }
                // A failure inside the transaction closes the entity manager
                if (!$this->em->isOpen()) {
                    throw $e;
                }
                continue;
            }

            foreach ($assetIds as $assetId) {
                $batch->state->markProcessed($assetId);
            }
        }
    }
}
