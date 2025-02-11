<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;

class AttributeDefinitionOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    public function supports(string $outputClass, object $data): bool
    {
        return AttributeDefinitionOutput::class === $outputClass && $data instanceof AttributeDefinition;
    }

    /**
     * @param AttributeDefinition $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
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
        $output->entityType = $data->getEntityType();
        $output->searchable = $data->isSearchable();
        $output->enabled = $data->isEnabled();
        $output->suggest = $data->isSuggest();
        $output->facetEnabled = $data->isFacetEnabled();
        $output->translatable = $data->isTranslatable();
        $output->multiple = $data->isMultiple();
        $output->allowInvalid = $data->isAllowInvalid();
        $output->searchBoost = $data->getSearchBoost();
        $output->fallback = $data->getFallback();
        $output->initialValues = $data->getInitialValues();
        if ($this->isGranted(AbstractVoter::EDIT, $data)) {
            $output->lastErrors = $data->getLastErrors();
        }
        $output->key = $data->getKey();
        $output->labels = $data->getLabels();
        $output->canEdit = $data->getClass()->isEditable()
            || $this->isGranted(PermissionInterface::EDIT, $data->getClass());
        $output->position = $data->getPosition();

        return $output;
    }
}
