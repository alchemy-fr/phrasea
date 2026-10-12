<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionDefinition;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AttributeDefinitionOutput::class)]
class AttributeDefinitionOutputMapper implements OutputMapperInterface
{
    use SecurityAwareTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof AttributeDefinition;
    }

    /**
     * @param AttributeDefinition $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AttributeDefinitionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->displayName = $data->getTranslatedField(AttributeDefinition::TR_FIELD_NAME, $this->getPreferredLocales($data->getWorkspace()), $data->getName());
        $output->workspace = $data->getWorkspace();
        $output->policy = $data->getPolicy();
        $output->name = $data->getName();
        $output->slug = $data->getSlug();
        $output->searchSlug = $this->fieldNameResolver->getFieldNameFromDefinition($data);
        $output->fileType = $data->getFileType();
        $output->type = $data->getType();
        $output->entityList = $data->getEntityList();
        $output->searchable = $data->isSearchable();
        $output->sortable = $data->isSortable();
        $output->enabled = $data->isEnabled();
        $output->suggest = $data->isSuggest();
        $output->facetEnabled = $data->isFacetEnabled();
        $output->translatable = $data->isTranslatable();
        $output->multiple = $data->isMultiple();
        $output->allowInvalid = $data->isAllowInvalid();
        $output->searchBoost = $data->getSearchBoost();
        $output->fallback = $data->getFallback() ?: null;
        $output->initialValues = $data->getInitialValues();
        $output->readFromMetadata = $data->getReadFromMetadata();
        $output->writeMetadataRenditions = array_map(
            fn (RenditionDefinition $renditionDefinition): string => $this->iriConverter->getIriFromResource($renditionDefinition),
            $data->getWriteMetadataRenditions()->getValues(),
        );
        $output->writeMetadata = $data->getWriteMetadata();
        $output->translations = $data->getTranslations();
        $output->target = $data->getTarget()->value;
        $output->key = $data->getKey();
        $output->position = $data->getPosition();
        $output->fillFromName = $data->isFillFromName();
        $output->namePriority = $data->getNamePriority();

        if ($this->hasGroup(AttributeDefinition::GROUP_LIST, $context)) {
            $output->labels = $data->getLabels();
            $output->editable = $data->isEditable();
            $output->required = $data->isRequired();
            $output->minLength = $data->getMinLength();
            $output->maxLength = $data->getMaxLength();
            $output->editableInGui = $data->isEditableInGui();
            if ($this->isGranted(AbstractVoter::EDIT, $data)) {
                $output->lastErrors = $data->getLastErrors();
            }
            $output->canEdit = $data->getPolicy()->isEditable()
                || $this->isGranted(PermissionInterface::EDIT, $data->getPolicy());
        }

        return $output;
    }
}
