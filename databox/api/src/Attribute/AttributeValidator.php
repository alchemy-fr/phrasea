<?php

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AttributeValidator
{
    public function validateAttributeInput(AbstractBaseAttributeInput $attributeInput, AttributeDefinition $definition): void
    {
        if ($attributeInput->locale
            && AttributeInterface::NO_LOCALE !== $attributeInput->locale
            && !$definition->isTranslatable()
        ) {
            throw new BadRequestHttpException(sprintf('Attribute "%s" is not translatable but locale was provided', $definition->getName()));
        }
    }
}
