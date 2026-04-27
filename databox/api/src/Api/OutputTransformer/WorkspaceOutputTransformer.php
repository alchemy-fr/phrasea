<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Api\Model\Output\WorkspaceOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\WorkspaceVoter;
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
        $output->nameTranslated = $data->getTranslatedField('name', $this->getPreferredLocales($data), $data->getName());
        $output->setSlug($data->getSlug());
        $output->setEnabledLocales($data->getEnabledLocales());
        $output->setPublic($data->isPublic());
        $output->setCreatedAt($data->getCreatedAt());
        $output->ownerId = $data->getOwnerId();

        if ($this->hasGroup([
            Workspace::GROUP_READ,
        ], $context)) {
            $output->setLocaleFallbacks($data->getLocaleFallbacks());
            $output->fileAnalyzers = $data->getFileAnalyzers();
            $output->trashRetentionDelay = $data->getTrashRetentionDelay();
            $output->translations = $data->getTranslations();
            $output->owner = $this->transformUser($data->getOwnerId());
        }

        if ($this->hasGroup([
            Collection::GROUP_LIST,
            Workspace::GROUP_LIST,
        ], $context)) {
            $k = $data->getId().$this->getUserCacheId();
            $output->setCapabilities($this->capCache->get($k, function () use ($data): array {
                return [
                    'createAsset' => $this->isGranted(AssetContainerVoterInterface::ASSET_CREATE, $data),
                    'createCollection' => $this->isGranted(WorkspaceVoter::CREATE_COLLECTION, $data),
                    'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                    'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                    'editPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
                ];
            }));
        }

        return $output;
    }
}
