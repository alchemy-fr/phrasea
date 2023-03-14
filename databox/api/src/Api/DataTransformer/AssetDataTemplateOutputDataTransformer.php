<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\Template\AssetDataTemplateOutput;
use App\Asset\Attribute\AttributesResolver;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Security\Core\Security;

class AssetDataTemplateOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private AttributesResolver $attributesResolver;
    private Security $security;

    public function __construct(
        AttributesResolver $attributesResolver,
        Security $security
    ) {
        $this->attributesResolver = $attributesResolver;
        $this->security = $security;
    }

    /**
     * @param AssetDataTemplate $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AssetDataTemplateOutput();
        $output->name = $object->getName();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->privacy = $object->getPrivacy();
        $output->tags = $object->getTags()->getValues();
        $output->public = $object->isPublic();
        $output->ownerId = $object->getOwnerId();
        $output->collection = $object->getCollection();
        $output->includeCollectionChildren = $object->isIncludeCollectionChildren();

        if (isset($context['groups']) && in_array('asset-data-template:read', $context['groups'], true)) {
            $output->attributes = array_filter($object->getAttributes()->getValues(), function (TemplateAttribute $attribute): bool {
                return $this->security->isGranted(AbstractVoter::READ, $attribute);
            });
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AbstractVoter::EDIT, $object),
            'canDelete' => $this->isGranted(AbstractVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetDataTemplateOutput::class === $to && $data instanceof AssetDataTemplate;
    }
}
