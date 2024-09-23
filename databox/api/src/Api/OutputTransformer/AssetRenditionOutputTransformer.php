<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Api\Model\Output\AssetRenditionOutput;
use App\Api\Model\Output\CollectionOutput;
use App\Asset\RenditionBuildHashManager;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use App\Storage\RenditionManager;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AssetRenditionOutputTransformer implements OutputTransformerInterface
{
    use GroupsHelperTrait;
    use SecurityAwareTrait;
    public function __construct(
        private readonly RenditionBuildHashManager $renditionBuildHashManager,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AssetRenditionOutput::class === $outputClass && $data instanceof AssetRendition;
    }

    /**
     * @param AssetRendition $data
     */
    public function transform($data, string $outputClass, array &$context = []): object
    {
        $output = new AssetRenditionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->asset = $data->getAsset();
        $definition = $data->getDefinition();
        $output->definition = $definition;
        $output->file = $data->getFile();
        $output->name = $data->getName();

        if ($this->hasGroup([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ], $context)) {
            $output->dirty = $this->renditionBuildHashManager->isRenditionDirty($data);
        }

        return $output;
    }
}
