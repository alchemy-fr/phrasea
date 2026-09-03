<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Api\Model\Output\WorkspaceOutput;
use App\Api\Model\Output\WorkspaceTermsOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\WorkspaceVoter;
use App\Service\Workspace\LogoManager;
use App\Service\Workspace\TermsManager;
use Symfony\Contracts\Cache\CacheInterface;

class WorkspaceOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use GroupsHelperTrait;
    use UserLocaleTrait;
    use UserOutputTransformerTrait;

    private CacheInterface $capCache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
        private readonly TermsManager $termsManager,
        private readonly LogoManager $logoManager,
        private readonly UrlSigner $urlSigner,
    ) {
        $this->capCache = $cacheFactory->createCache();
    }

    public function supports(string $outputClass, object $data): bool
    {
        return WorkspaceOutput::class === $outputClass && $data instanceof Workspace;
    }

    /**
     * @param Workspace $data
     */
    public function transform($data, string $outputClass, array &$context = []): object
    {
        $output = new WorkspaceOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->displayName = $data->getTranslatedField(Workspace::TR_FIELD_NAME, $this->getPreferredLocales($data), $data->getName());
        $output->setSlug($data->getSlug());
        $output->setEnabledLocales($data->getEnabledLocales());
        $output->setPublic($data->isPublic());
        $output->setCreatedAt($data->getCreatedAt());
        $output->ownerId = $data->getOwnerId();

        if ($this->hasGroup([
            Workspace::GROUP_READ,
        ], $context)) {
            $output->setLocaleFallbacks($data->getLocaleFallbacks());
            $output->trashRetentionDelay = $data->getTrashRetentionDelay();
            $output->assetDefaultStatus = $data->getAssetDefaultStatus();
            $output->fileAnalysisRequired = $data->isFileAnalysisRequired();
            $output->translations = $data->getTranslations();
            $output->owner = $this->transformUser($data->getOwnerId());

            $currentTerms = $this->termsManager->getCurrentTerms($data);
            if (null !== $currentTerms) {
                $userId = $this->getUser()?->getId();
                $output->terms = new WorkspaceTermsOutput(
                    $currentTerms->hasPdf() ? null : $currentTerms->getText(),
                    $currentTerms->getVersion(),
                    null !== $userId ? $this->termsManager->hasSigned($currentTerms, $userId) : null,
                    $data->isAttachTermsToExports(),
                    $currentTerms->hasPdf() ? $this->urlSigner->getSignedUrl($currentTerms->getPdfPath()) : null,
                );
            }
        }

        $output->logo = $this->logoManager->resolveLogoUrl($data);

        if ($this->hasGroup([
            Collection::GROUP_LIST,
            Workspace::GROUP_LIST,
        ], $context)) {
            $k = $data->getId().$this->getUserCacheId();
            $output->setCapabilities($this->capCache->get($k, fn (): array => [
                'createAsset' => $this->isGranted(AssetContainerVoterInterface::ASSET_CREATE, $data),
                'createCollection' => $this->isGranted(WorkspaceVoter::CREATE_COLLECTION, $data),
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'editPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]));
        }

        return $output;
    }
}
