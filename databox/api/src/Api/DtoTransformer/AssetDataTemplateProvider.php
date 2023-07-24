<?php

declare(strict_types=1);

namespace App\Api\DtoTransformer;

use App\Api\Model\Output\Template\AssetDataTemplateOutput;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;

class AssetDataTemplateProvider implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    /**
     * @param AssetDataTemplate $data
     */
    public function transform(object $data, string $outputClass, array $context = []): object
    {
        $output = new AssetDataTemplateOutput();
        $output->name = $data->getName();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->privacy = $data->getPrivacy();
        $output->tags = $data->getTags()->getValues();
        $output->public = $data->isPublic();
        $output->ownerId = $data->getOwnerId();
        $output->collection = $data->getCollection();
        $output->includeCollectionChildren = $data->isIncludeCollectionChildren();

        if (isset($context['groups']) && in_array(AssetDataTemplate::GROUP_READ, $context['groups'], true)) {
            $output->attributes = array_filter($data->getAttributes()->getValues(), fn (TemplateAttribute $attribute): bool => $this->security->isGranted(AbstractVoter::READ, $attribute));
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
            'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
            'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
        ]);

        return $output;
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AssetDataTemplateOutput::class === $outputClass && $data instanceof AssetDataTemplate;
    }
}
