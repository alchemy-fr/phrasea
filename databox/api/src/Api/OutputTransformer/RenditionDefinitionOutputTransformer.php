<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\RenditionDefinitionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\RenditionDefinition;
use App\Security\Voter\RenditionDefinitionVoter;

class RenditionDefinitionOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserLocaleTrait;

    public function supports(string $outputClass, object $data): bool
    {
        return RenditionDefinitionOutput::class === $outputClass && $data instanceof RenditionDefinition;
    }

    /**
     * @param RenditionDefinition $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new RenditionDefinitionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->workspace = $data->getWorkspace();
        $output->policy = $data->getPolicy();
        $output->name = $data->getName();
        $output->nameTranslated = $data->getTranslatedField('name', $this->getPreferredLocales($data->getWorkspace()), $data->getName());
        $output->parent = $data->getParent();
        $output->download = $data->isDownload();
        $output->substitutable = $data->isSubstitutable();
        $output->labels = $data->getLabels();
        $output->translations = $data->getTranslations();
        $output->target = $data->getTarget()->value;

        if ($this->isGranted(RenditionDefinitionVoter::READ_ADMIN, $data)) {
            $output->buildMode = $data->getBuildMode();
            $output->useAsOriginal = $data->isUseAsOriginal();
            $output->useAsPreview = $data->isUseAsPreview();
            $output->useAsThumbnail = $data->isUseAsThumbnail();
            $output->useAsThumbnailActive = $data->isUseAsThumbnailActive();
            $output->definition = $data->getDefinition();
            $output->priority = $data->getPriority();
        }

        return $output;
    }
}
