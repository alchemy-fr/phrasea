<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\Template\AssetDataTemplateOutput;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;

class AssetDataTemplateOutputProcessor extends AbstractSecurityProcessor
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @param AssetDataTemplate $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
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

        if (isset($context['groups']) && in_array('asset-data-template:read', $context['groups'], true)) {
            $output->attributes = array_filter($data->getAttributes()->getValues(), fn (TemplateAttribute $attribute): bool => $this->security->isGranted(AbstractVoter::READ, $attribute));
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
            'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
            'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetDataTemplateOutput::class === $to && $data instanceof AssetDataTemplate;
    }
}
