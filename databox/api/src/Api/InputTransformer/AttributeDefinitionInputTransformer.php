<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Attribute\Type\AttributeTypeChangeService;
use App\Attribute\Type\EntityAttributeType;
use App\Consumer\Handler\Attribute\AttributeMigrateFromEntityList;
use App\Consumer\Handler\Attribute\AttributeMigrateToEntityList;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributeDefinitionInputTransformer extends AbstractInputTransformer
{
    public function __construct(
        private readonly AttributeTypeChangeService $attributeTypeChangeService,
        private readonly PostFlushStack $postFlushStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return AttributeDefinition::class === $resourceClass && $data instanceof AttributeDefinitionInput;
    }

    /**
     * @param AttributeDefinitionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $this->validator->validate($data, $context);

        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var AttributeDefinition $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AttributeDefinition();

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        }

        if ($isNew) {
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
                $attrDef = $this->em->getRepository(AttributeDefinition::class)
                    ->findByKey($data->key, $workspace->getId());

                if ($attrDef) {
                    $isNew = false;
                    $object = $attrDef;
                }
            }
        }

        if ($isNew) {
            $object->setWorkspace($workspace);
            $object->setKey($data->key);
        }

        if ($data->policy) {
            $object->setPolicy($data->policy);
        }
        if ($data->allowInvalid) {
            $object->setAllowInvalid($data->allowInvalid);
        }
        if (null !== $data->fallback) {
            $object->setFallback($data->fallback);
        }
        if (null !== $data->initialValues) {
            $object->setInitialValues($data->initialValues);
        }
        if (null !== $newType = $data->fieldType) {
            $previousType = $object->getFieldType();

            if ($newType !== $previousType) {
                $this->attributeTypeChangeService->handleTypeChange($previousType, $newType, $object);
                $object->setFieldType($newType);

                if (EntityAttributeType::NAME === $newType) {
                    $this->postFlushStack->addBusMessage(new AttributeMigrateToEntityList($object->getId()));
                } elseif (EntityAttributeType::NAME === $previousType) {
                    if (!$object->getEntityList()) {
                        $this->logger->alert('Attribute was changed from Entity type but has no entity list, skipping migration', [
                            'attributeId' => $object->getId(),
                            'newType' => $newType,
                        ]);
                    } else {
                        $this->postFlushStack->addBusMessage(new AttributeMigrateFromEntityList($object->getId(), $object->getEntityList()->getId()));
                    }
                }
            }
        }

        if (EntityAttributeType::NAME === $object->getFieldType()) {
            if (null !== $data->entityList) {
                $object->setEntityList($data->entityList);
            }
        } else {
            $object->setEntityList(null);
        }

        if (null !== $data->fileType) {
            $object->setFileType($data->fileType);
        }
        if (null !== $data->searchable) {
            $object->setSearchable($data->searchable);
        }
        if (null !== $data->suggest) {
            $object->setSuggest($data->suggest);
        }
        if (null !== $data->enabled) {
            $object->setEnabled($data->enabled);
        }
        if (null !== $data->sortable) {
            $object->setSortable($data->sortable);
        }
        if (null !== $data->facetEnabled) {
            $object->setFacetEnabled($data->facetEnabled);
        }
        if (null !== $data->multiple) {
            $object->setMultiple($data->multiple);
        }
        if (null !== $data->editable) {
            $object->setEditable($data->editable);
        }
        if (null !== $data->editableInGui) {
            $object->setEditableInGui($data->editableInGui);
        }
        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->translatable) {
            $object->setTranslatable($data->translatable);
        }
        if (null !== $data->labels) {
            $object->setLabels($data->labels);
        }
        if (null !== $data->position) {
            $object->setPosition($data->position);
        }
        if (null !== $data->translations) {
            $object->setTranslations($data->translations);
        }
        if (null !== $data->target) {
            $object->setTarget(AssetTypeEnum::tryFrom($data->target) ?? AssetTypeEnum::Asset);
        }

        return $object;
    }
}
