<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeDefinitionInputTransformer extends AbstractInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return AttributeDefinition::class === $resourceClass && $data instanceof AttributeDefinitionInput;
    }

    /**
     * @param AttributeDefinitionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
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
}
