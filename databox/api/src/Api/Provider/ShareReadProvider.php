<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\StorageBundle\Storage\UrlSigner;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ShareAlternateUrlOutput;
use App\Api\Model\Output\ShareAttachmentOutput;
use App\Api\Model\Output\ShareTermsOutput;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Workspace\LogoManager;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;

final class ShareReadProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TermsManager $termsManager,
        private readonly LogoManager $logoManager,
        private readonly UrlSigner $urlSigner,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$item instanceof Share) {
            return $item;
        }

        return $this->provideShare($item);
    }

    public function provideShare(Share $item): Share
    {
        $item->alternateUrls = [];
        $item->attachments = [];

        foreach ($item->getAssetsList() as $asset) {
            $this->provideAssetAlternateUrls($item, $asset);
            $this->provideAssetAttachments($item, $asset);
        }

        $workspace = $item->getWorkspace();
        if (null !== $workspace) {
            $item->logo = $this->logoManager->resolveLogoUrl($workspace);

            $terms = $this->termsManager->getCurrentTerms($workspace);
            if (null !== $terms) {
                $item->terms = new ShareTermsOutput(
                    $terms->hasPdf() ? null : $terms->getText(),
                    $terms->getVersion(),
                    $workspace->getName(),
                    $terms->hasPdf() ? $this->urlSigner->getSignedUrl($terms->getPdfPath()) : null,
                );
            }
        }

        return $item;
    }

    private function provideAssetAlternateUrls(Share $item, Asset $asset): void
    {
        $options = [
            AssetRenditionRepository::OPT_WITH_FILE => true,
        ];

        /** @var AssetRendition[] $renditions */
        $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($asset->getId(), $options);

        foreach ($renditions as $rendition) {
            $definition = $rendition->getDefinition();
            if (null === $definition) {
                continue;
            }
            if ($this->isGranted(AbstractVoter::READ, $rendition)) {
                $item->alternateUrls[] = new ShareAlternateUrlOutput(
                    $definition->getName(),
                    $this->urlGenerator->generate('share_public_rendition', [
                        'id' => $item->getId(),
                        'rendition' => $definition->getId(),
                        'asset' => $asset->getId(),
                        'token' => $item->getToken(),
                    ], UrlGeneratorInterface::ABS_URL),
                    $rendition->getFile()->getType(),
                    $asset->getId(),
                );
            }
        }
    }

    private function provideAssetAttachments(Share $item, Asset $asset): void
    {
        foreach ($asset->getAttachments() as $attachment) {
            $attachedAsset = $attachment->getAttachment();
            $file = $attachedAsset?->getSource();
            if (null === $file) {
                continue;
            }

            $item->attachments[] = new ShareAttachmentOutput(
                $attachment->getId(),
                $attachment->getName() ?? $file->getFileName(),
                $asset->getId(),
                $this->urlGenerator->generate('share_public_attachment', [
                    'id' => $item->getId(),
                    'attachment' => $attachment->getId(),
                    'token' => $item->getToken(),
                ], UrlGeneratorInterface::ABS_URL),
                $file->getType(),
                null !== $file->getSize() ? (int) $file->getSize() : null,
            );
        }
    }
}
