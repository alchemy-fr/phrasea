<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AclBundle\Security\PermissionInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Entity\Core\AttributeDefinition;

class AttributeDefinitionOutputProcessor extends AbstractSecurityProcessor
{
    /**
     * @param AttributeDefinition $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new AttributeDefinitionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->workspace = $data->getWorkspace();
        $output->class = $data->getClass();
        $output->name = $data->getName();
        $output->slug = $data->getSlug();
        $output->fileType = $data->getFileType();
        $output->fieldType = $data->getFieldType();
        $output->searchable = $data->isSearchable();
        $output->facetEnabled = $data->isFacetEnabled();
        $output->translatable = $data->isTranslatable();
        $output->multiple = $data->isMultiple();
        $output->allowInvalid = $data->isAllowInvalid();
        $output->searchBoost = $data->getSearchBoost();
        $output->fallback = $data->getFallback();
        $output->initialValues = $data->getInitialValues();
        $output->key = $data->getKey();
        $output->canEdit = $data->getClass()->isEditable()
            || $this->isGranted(PermissionInterface::EDIT, $data->getClass());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeDefinitionOutput::class === $to && $data instanceof AttributeDefinition;
    }
}
