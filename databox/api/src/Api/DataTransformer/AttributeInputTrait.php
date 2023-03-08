<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends AbstractInputDataTransformer
 */
trait AttributeInputTrait
{
    protected function getAttributeDefinitionFromInput(AbstractBaseAttributeInput $data, ?Workspace $workspace): AttributeDefinition
    {
        $definition = null;
        if ($data->definitionId) {
            $definition = $this->em->getRepository(AttributeDefinition::class)->find($data->definitionId);
        } elseif ($data->name && null !== $workspace) {
            $definition = $context[AttributeInputDataTransformerInterface::ATTRIBUTE_DEFINITION] ?? $this->em->getRepository(AttributeDefinition::class)->findOneBy([
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
     * @param Asset|AssetDataTemplate $object
     * @param AbstractBaseAttributeInput[] $attributes
     *
     * @return void
     */
    protected function assignAttributes(AbstractInputDataTransformer $attributeInputDataTransformer, $object, iterable $attributes, string $to, array $context)
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof AttributeInput) {
                $attribute->asset = $object;
            } elseif ($attribute instanceof TemplateAttributeInput) {
                $attribute->template = $object;
            }

            if (is_array($attribute->value)) {
                $definition = $this->getAttributeDefinitionFromInput($attribute, $object->getWorkspace());

                if ($definition->isMultiple()) {
                    foreach ($attribute->value as $value) {
                        $attr = clone $attribute;
                        $attr->value = $value;
                        /** @var Attribute|TemplateAttribute $returnedAttribute */
                        $returnedAttribute = $attributeInputDataTransformer->transform($attr, $to, array_merge([
                            AttributeInputDataTransformerInterface::ATTRIBUTE_DEFINITION => $definition,
                        ], $context));
                        $object->addAttribute($returnedAttribute);
                    }

                    continue;
                }
                // else add single attr below
            }

            /** @var Attribute|TemplateAttribute $returnedAttribute */
            $returnedAttribute = $attributeInputDataTransformer->transform($attribute, $to, $context);
            $object->addAttribute($returnedAttribute);
        }
    }
}
