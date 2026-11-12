<?php

declare(strict_types=1);

namespace App\Service\Share;

use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Share;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * What a share link delivers for the attachments of its assets: a rendition the
 * link shares (see ShareRenditionUrlGenerator::getSharedRenditions()), never the
 * original, and only while the attached asset is neither trashed nor quarantined
 * and the owner of the share may still read it.
 *
 * Evaluated at each access: the owner's roles and groups are read from the IdP.
 */
final readonly class ShareAttachmentResolver
{
    public function __construct(
        private ShareRenditionUrlGenerator $renditionUrlGenerator,
        private UserContextSwitcher $userContextSwitcher,
        private Security $security,
    ) {
    }

    /**
     * The attachments of the deliverable assets of the share, in their order.
     *
     * @return SharedAttachment[]
     */
    public function getSharedAttachments(Share $share): array
    {
        $candidates = [];
        foreach ($share->getDeliverableAssets() as $asset) {
            foreach ($asset->getAttachments() as $attachment) {
                if (null !== $rendition = $this->getCandidateRendition($share, $attachment)) {
                    $candidates[] = new SharedAttachment($attachment, $rendition);
                }
            }
        }

        $readable = $this->getAssetsReadableByOwner($share, array_map(
            static fn (SharedAttachment $shared): Asset => $shared->rendition->getAsset(),
            $candidates,
        ));

        return array_values(array_filter(
            $candidates,
            static fn (SharedAttachment $shared): bool => isset($readable[$shared->rendition->getAsset()->getId()]),
        ));
    }

    /**
     * The rendition the share link delivers for this attachment, null when none.
     * The caller checks that the attachment belongs to a deliverable asset of the share.
     */
    public function getSharedRendition(Share $share, AssetAttachment $attachment): ?AssetRendition
    {
        $rendition = $this->getCandidateRendition($share, $attachment);
        if (null === $rendition || empty($this->getAssetsReadableByOwner($share, [$rendition->getAsset()]))) {
            return null;
        }

        return $rendition;
    }

    private function getCandidateRendition(Share $share, AssetAttachment $attachment): ?AssetRendition
    {
        $attached = $attachment->getAttachment();
        if (null === $attached || $attached->isDeleted() || AssetStatusEnum::Accepted !== $attached->getStatus()) {
            return null;
        }

        // The main rendition, else the preview, else the first one (they come ordered by priority)
        $renditions = $this->renditionUrlGenerator->getSharedRenditions($share, $attached);
        foreach ([
            static fn (AssetRendition $r): bool => true === $r->getDefinition()?->isUseAsMain(),
            static fn (AssetRendition $r): bool => true === $r->getDefinition()?->isUseAsPreview(),
            static fn (AssetRendition $r): bool => true,
        ] as $matches) {
            foreach ($renditions as $rendition) {
                if ($matches($rendition)) {
                    return $rendition;
                }
            }
        }

        return null;
    }

    /**
     * @param Asset[] $assets
     *
     * @return array<string, true> the IDs of the assets the owner of the share may read
     */
    private function getAssetsReadableByOwner(Share $share, array $assets): array
    {
        $ownerId = $share->getOwnerId();
        if (null === $ownerId || empty($assets)) {
            return [];
        }

        try {
            return $this->userContextSwitcher->runAs($ownerId, function () use ($assets): array {
                $readable = [];
                foreach ($assets as $asset) {
                    if ($this->security->isGranted(AbstractVoter::READ, $asset)) {
                        $readable[$asset->getId()] = true;
                    }
                }

                return $readable;
            });
        } catch (AutomationOwnerUnavailableException) {
            return [];
        }
    }
}
