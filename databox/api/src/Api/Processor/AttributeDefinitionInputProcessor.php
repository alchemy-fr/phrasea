<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeDefinitionInputProcessor extends AbstractInputProcessor
{
    /**
     * @param AttributeDefinitionInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var AttributeDefinition $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new AttributeDefinition();

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

        if ($data->class) {
            $object->setClass($data->class);
        }
        if ($data->allowInvalid) {
            $object->setAllowInvalid($data->allowInvalid);
        }
        if (null !== $data->fallback) {
            $object->setFallback($data->fallback);
        }
        if (null !== $data->fieldType) {
            $object->setFieldType($data->fieldType);
        }
        if (null !== $data->fileType) {
            $object->setFileType($data->fileType);
        }
        if (null !== $data->searchable) {
            $object->setSearchable($data->searchable);
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
        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->translatable) {
            $object->setTranslatable($data->translatable);
        }

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof AttributeDefinition) {
            return false;
        }

        return AttributeDefinition::class === $to && AttributeDefinitionInput::class === ($context['input']['class'] ?? null);
    }
}