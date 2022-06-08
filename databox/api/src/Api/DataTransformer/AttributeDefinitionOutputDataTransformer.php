<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Entity\Core\AttributeDefinition;

class AttributeDefinitionOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param AttributeDefinition $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AttributeDefinitionOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->workspace = $object->getWorkspace();
        $output->class = $object->getClass();
        $output->name = $object->getName();
        $output->fileType = $object->getFileType();
        $output->fieldType = $object->getFieldType();
        $output->searchable = $object->isSearchable();
        $output->facetEnabled = $object->isFacetEnabled();
        $output->translatable = $object->isTranslatable();
        $output->multiple = $object->isMultiple();
        $output->allowInvalid = $object->isAllowInvalid();
        $output->searchBoost = $object->getSearchBoost();
        $output->fallback = $object->getFallback();
        $output->key = $object->getKey();
        $output->canEdit = $object->getClass()->isEditable()
            || $this->isGranted(PermissionInterface::EDIT, $object->getClass());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeDefinitionOutput::class === $to && $data instanceof AttributeDefinition;
    }
}
