<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeInputDataTransformer extends AbstractInputDataTransformer
{
    public const ATTRIBUTE_DEFINITION = '_ATTR_DEF';

    private AttributeAssigner $attributeAssigner;

    public function __construct(AttributeAssigner $attributeAssigner)
    {
        $this->attributeAssigner = $attributeAssigner;
    }

    /**
     * @param AttributeInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Attribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);

            $definition = null;
            if ($data->definition) {
                $definition = $data->definition;
            } elseif ($data->name && $object->getAsset()) {
                $definition = $context[self::ATTRIBUTE_DEFINITION] ?? $this->em->getRepository(AttributeDefinition::class)->findOneBy([
                    'name' => $data->name,
                    'workspace' => $object->getAsset()->getWorkspaceId(),
                ]);

                if (!$definition instanceof AttributeDefinition) {
                    throw new BadRequestHttpException(sprintf('Attribute definition "%s" not found', $data->name));
                }
            }

            if ($definition instanceof AttributeDefinition) {
                $object->setDefinition($definition);
            }
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Attribute) {
            return false;
        }

        return Attribute::class === $to && AttributeInput::class === ($context['input']['class'] ?? null);
    }
}
