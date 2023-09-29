<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Api\Processor\AttributeInputProcessorInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @extends AbstractInputTransformer
 */
trait AttributeInputTrait
{
    protected function getAttributeDefinitionFromInput(AbstractBaseAttributeInput $data, ?Workspace $workspace, array $context): AttributeDefinition
    {
        $definition = null;
        if (isset($context[AttributeInputProcessorInterface::ATTRIBUTE_DEFINITION])) {
            $definition = $context[AttributeInputProcessorInterface::ATTRIBUTE_DEFINITION];
        } elseif ($data->definitionId) {
            $definition = $this->em->getRepository(AttributeDefinition::class)->find($data->definitionId);
        } elseif ($data->name && null !== $workspace) {
            $definition = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
                'name' => $data->name,
                'workspace' => $workspace->getId(),
            ]);
        }

        if (!$definition instanceof AttributeDefinition) {
            throw new BadRequestHttpException('Missing Attribute definition');
        }
        if (null !== $workspace && $definition->getWorkspaceId() !== $workspace->getId()) {
            throw new BadRequestHttpException('Workspace inconsistency');
        }

        return $definition;
    }

    /**
     * @param AbstractBaseAttributeInput[] $attributes
     */
    protected function assignAttributes(
        AbstractInputTransformer $attributeInputProcessor,
        Asset|AssetDataTemplate $object,
        iterable $attributes,
        array $context
    ): void {
        unset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

        foreach ($attributes as $attribute) {
            if ($attribute instanceof AttributeInput) {
                $attribute->asset = $object;
            } elseif ($attribute instanceof TemplateAttributeInput) {
                $attribute->template = $object;
            }

            $definition = $this->getAttributeDefinitionFromInput($attribute, $object->getWorkspace(), $context);
            $subContext = array_merge($context, [
                AttributeInputProcessorInterface::ATTRIBUTE_DEFINITION => $definition,
            ]);

            if (is_array($attribute->value)) {
                if ($definition->isMultiple()) {
                    foreach ($attribute->value as $value) {
                        $attr = clone $attribute;
                        $attr->value = $value;
                        /** @var Attribute|TemplateAttribute $returnedAttribute */
                        $returnedAttribute = $attributeInputProcessor->transform($attr, Attribute::class, $subContext);
                        $object->addAttribute($returnedAttribute);
                    }

                    continue;
                }
                // else add single attr below
            }

            /** @var Attribute|TemplateAttribute $returnedAttribute */
            $returnedAttribute = $attributeInputProcessor->transform($attribute, Attribute::class, $subContext);
            $object->addAttribute($returnedAttribute);
        }
    }
}
