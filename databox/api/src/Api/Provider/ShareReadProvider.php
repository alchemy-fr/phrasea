<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ShareAlternateUrlOutput;
use App\Api\Model\Output\ShareAttachmentOutput;
use App\Api\Model\Output\ShareTermsOutput;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Entity\Core\TermsVersion;
use App\Model\ShareTokenAccessEnum;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\FileUrlResolver;
use App\Service\Share\ShareAccessManager;
use App\Service\Share\ShareAttachmentResolver;
use App\Service\Share\ShareRenditionUrlGenerator;
use App\Service\Workspace\LogoManager;
use App\Service\Workspace\TermsManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ShareReadProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;
    use SecurityAwareTrait;
    use UserLocaleTrait;

    final public const string PUBLIC_OPERATION = 'share_public';
    final public const string ERROR_PASSWORD_REQUIRED = 'share_password_required';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TermsManager $termsManager,
        private readonly LogoManager $logoManager,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly ShareRenditionUrlGenerator $renditionUrlGenerator,
        private readonly ShareAttachmentResolver $attachmentResolver,
        private readonly ShareAccessManager $shareAccessManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$item instanceof Share) {
            return $item;
        }

        if (self::PUBLIC_OPERATION === $operation->getName() && !$this->isGranted(AbstractVoter::READ, $item)) {
            if (ShareTokenAccessEnum::PasswordRequired === $this->shareAccessManager->resolveTokenAccess($item)) {
                return new JsonResponse(['error' => self::ERROR_PASSWORD_REQUIRED], Response::HTTP_FORBIDDEN);
            }

            throw new AccessDeniedException();
        }

        return $this->provideShare($item);
    }

    public function provideShare(Share $item): Share
    {
        $item->alternateUrls = [];
        $item->attachments = [];

        foreach ($item->getDeliverableAssets() as $asset) {
            $this->provideAssetAlternateUrls($item, $asset);
        }
        $this->provideAttachments($item);

        $workspace = $item->getWorkspace();
        if (null !== $workspace) {
            $item->logo = $this->logoManager->resolveLogoUrl($workspace);

            $terms = $this->termsManager->getCurrentTerms($workspace);
            if (null !== $terms) {
                $item->terms = new ShareTermsOutput(
                    $terms->hasFile() ? null : $terms->getTranslatedField(TermsVersion::TR_FIELD_TEXT, $this->getPreferredLocales($workspace), $terms->getText()),
                    $terms->getVersion(),
                    $workspace->getName(),
                    $terms->hasFile() ? $this->fileUrlResolver->resolveUrl($terms->getFile()) : null,
                );
            }
        }

        return $item;
    }

    /**
     * A share protected by a password gives no durable link: its URLs are the
     * temporary signed URLs of the storage, never the permalinks of the share.
     */
    private function provideAssetAlternateUrls(Share $item, Asset $asset): void
    {
        foreach ($this->renditionUrlGenerator->getSharedRenditions($item, $asset) as $rendition) {
            $definition = $rendition->getDefinition();
            $file = $rendition->getFile();
            $url = $item->getHasPassword()
                ? $this->fileUrlResolver->resolveUrl($file)
                : $this->renditionUrlGenerator->generateUrl($item, $asset, $definition);
            if (null === $url) {
                continue;
            }

            $item->alternateUrls[] = new ShareAlternateUrlOutput(
                $definition->getName(),
                $url,
                $file->getType(),
                $asset->getId(),
                $rendition->getId(),
                $definition->getId(),
                $definition->getTranslatedField(RenditionDefinition::TR_FIELD_NAME, $this->getPreferredLocales($definition->getWorkspace()), $definition->getName()),
                null !== $file->getSize() ? (int) $file->getSize() : null,
            );
        }
    }

    /**
     * Whether a share link exposes this rendition: a definition of a public
     * policy, in the selection of the link if any. A link is opened anonymously
     * with its token: the rule must only depend on the share and the rendition
     * definition, never on the rights of the current user.
     */
    public static function isRenditionShared(Share $share, AssetRendition $rendition): bool
    {
        $definition = $rendition->getDefinition();

        return null !== $definition
            && true === $definition->getPolicy()?->isPublic()
            && $share->isRenditionDefinitionShared($definition);
    }

    private function provideAttachments(Share $item): void
    {
        foreach ($this->attachmentResolver->getSharedAttachments($item) as $shared) {
            $attachment = $shared->attachment;
            $file = $shared->rendition->getFile();
            $url = $item->getHasPassword()
                ? $this->fileUrlResolver->resolveUrl($file)
                : $this->urlGenerator->generate('share_public_attachment', [
                    'id' => $item->getId(),
                    'attachment' => $attachment->getId(),
                    'token' => $item->getToken(),
                ], UrlGeneratorInterface::ABS_URL);
            if (null === $url) {
                continue;
            }

            $item->attachments[] = new ShareAttachmentOutput(
                $attachment->getId(),
                $attachment->getName() ?? $file->getFileName(),
                $attachment->getAsset()->getId(),
                $url,
                $file->getType(),
                null !== $file->getSize() ? (int) $file->getSize() : null,
            );
        }
    }
}
