<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Automation\AutomationBatch;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Changes the privacy of the assets. As with PATCH /assets/{id}, the privacy is only
 * changed on the assets the owner of the saved search may edit and whose permissions
 * he may edit. The updated assets are reindexed like any asset update.
 */
#[AsTaggedItem(index: self::TYPE)]
final class SetPrivacyAction extends AbstractAutomationAction
{
    public const string TYPE = 'set_privacy';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $privacy = $config['privacy'] ?? null;
        if (is_string($privacy) && false !== $key = array_search($privacy, WorkspaceItemPrivacyInterface::KEYS, true)) {
            $privacy = $key;
        }
        if (!is_int($privacy) || !isset(WorkspaceItemPrivacyInterface::KEYS[$privacy])) {
            self::throwViolations(['privacy' => sprintf(
                'The privacy must be one of: %s.',
                implode(', ', array_keys(WorkspaceItemPrivacyInterface::KEYS)),
            )]);
        }

        return ['privacy' => $privacy];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $privacy = $batch->config['privacy'];

        $changed = [];
        foreach ($batch->assets as $asset) {
            if (!$this->isGranted(AbstractVoter::EDIT, $asset)
                || !$this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $asset)) {
                $batch->state->markSkipped($asset, 'Not allowed to change the privacy of the asset');
                continue;
            }

            if ($asset->getPrivacy() === $privacy) {
                $batch->state->markProcessed($asset);
                continue;
            }

            $asset->setPrivacy($privacy);
            $changed[] = $asset;
        }

        if (empty($changed)) {
            return;
        }

        $this->em->flush();
        foreach ($changed as $asset) {
            $batch->state->markProcessed($asset);
        }
    }
}
