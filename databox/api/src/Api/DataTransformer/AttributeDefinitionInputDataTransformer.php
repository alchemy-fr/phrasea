<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Api\Model\Input\AttributeInput;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeDefinitionInputDataTransformer extends AbstractInputDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param AttributeDefinitionInput $data
     */
    public function transform($data, string $to, array $context = [])
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

        if ($data->allowInvalid) {
            $object->setAllowInvalid($data->allowInvalid);
        }
        if ($data->editable) {
            $object->setEditable($data->editable);
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
        if (null !== $data->multiple) {
            $object->setMultiple($data->multiple);
        }
        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->translatable) {
            $object->setTranslatable($data->translatable);
        }
        if (null !== $data->public) {
            $object->setPublic($data->public);
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
