<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AssetRenditionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Asset\RenditionBuildHashManager;
use App\Entity\Core\AssetRendition;

final class AssetRenditionOutputTransformer implements OutputTransformerInterface
{
    use GroupsHelperTrait;
    use SecurityAwareTrait;
    use UserLocaleTrait;

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
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->asset = $data->getAsset();
        $definition = $data->getDefinition();
        $output->definition = $definition;
        $output->file = $data->getFile();
        $output->name = $data->getName();
        $output->nameTranslated = $definition->getTranslatedField('name', $this->getPreferredLocales($definition->getWorkspace()), $definition->getName());
        $output->projection = $data->getProjection();
        $output->locked = $data->isLocked();
        $output->substituted = $data->isSubstituted();

        if ($this->hasGroup([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ], $context)) {
            $output->dirty = $this->renditionBuildHashManager->isRenditionDirty($data);
        }

        return $output;
    }
}
