<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AssetRenditionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Service\Asset\RenditionBuildHashManager;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AssetRenditionOutput::class)]
final class AssetRenditionOutputMapper implements OutputMapperInterface
{
    use GroupsHelperTrait;
    use SecurityAwareTrait;
    use UserLocaleTrait;

    public function __construct(
        private readonly RenditionBuildHashManager $renditionBuildHashManager,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof AssetRendition;
    }

    /**
     * @param AssetRendition $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AssetRenditionOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->asset = $data->getAsset();
        $definition = $data->getDefinition();
        $output->definition = $definition;
        $output->file = $data->getFile();
        $output->name = $data->getName();
        $output->displayName = null !== $definition
            ? $definition->getTranslatedField(RenditionDefinition::TR_FIELD_NAME, $this->getPreferredLocales($definition->getWorkspace()), $definition->getName())
            : $data->getName();
        $output->projection = $data->getProjection();
        $output->locked = $data->isLocked();
        $output->substituted = $data->isSubstituted();
        $output->ready = $data->isReady();

        if ($this->hasGroup([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ], $context)) {
            $output->dirty = $this->renditionBuildHashManager->isRenditionDirty($data);
        }

        return $output;
    }
}
