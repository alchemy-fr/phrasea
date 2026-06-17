<?php

namespace App\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\DBAL\Types\ConversionException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AttributeValidator
{
    final public const string TAGS = 'tags';

    public function __construct(
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
        private Security $security,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeRepository $attributeRepository,
        private AttributeTypeRegistry $typeRegistry,
    ) {
    }

    public function validateAttribute(
        AttributeDefinition $definition,
        AbstractBaseAttributeInput $attributeInput,
        string $contextName,
    ): void {
        $validationContext = new ExecutionContext(
            $this->validator,
            $contextName,
            $this->translator,
        );

        $this->doValidateAttribute($definition, $attributeInput, $validationContext);
    }

    private function doValidateAttribute(
        AttributeDefinition $definition,
        AbstractBaseAttributeInput $attributeInput,
        ExecutionContextInterface $validationContext,
    ): void {
        if ($attributeInput->locale
            && AttributeInterface::NO_LOCALE !== $attributeInput->locale
            && !$definition->isTranslatable()
        ) {
            throw new BadRequestHttpException(sprintf('Attribute "%s" is not translatable but locale was provided', $definition->getName()));
        }

        $value = $attributeInput->value;
        if (null !== $value) {
            $type = $this->typeRegistry->getStrictType($definition->getType());

            if ($definition->isMultiple()) {
                if (!is_array($value)) {
                    $value = [$value];
                }

                foreach ($value as $j => $v) {
                    $validationContext->setNode($value, $attributeInput, null, sprintf('%s.value[%s]', $validationContext->getPropertyPath(), $j));
                    if (null !== $v) {
                        $this->addErrorsToContext($definition, $attributeInput, $validationContext, $type->validate($v));
                    }
                }
            } else {
                $validationContext->setNode($value, $attributeInput, null, sprintf('%s.value', $validationContext->getPropertyPath()));
                $this->addErrorsToContext($definition, $attributeInput, $validationContext, $type->validate($value));
            }
        }
    }

    private function addErrorsToContext(AttributeDefinition $definition, AbstractBaseAttributeInput $attributeInput, ExecutionContextInterface $validationContext, ?array $errors): void
    {
        if (!empty($errors)) {
            $attributeInput->errors = $errors;
            if (!$definition->isAllowInvalid()) {
                foreach ($errors as $error) {
                    $validationContext->addViolation($error);
                }
            }
        }
    }

    /**
     * @param AbstractBaseAttributeInput[] $attributes
     */
    public function validateAttributeInputs(string $workspaceId, array $attributes, string $contextName): void
    {
        $validationContext = new ExecutionContext(
            $this->validator,
            $contextName,
            $this->translator,
        );

        foreach ($attributes as $i => $attributeInput) {
            $definition = null;
            if ($attributeInput->definition) {
                $definition = $attributeInput->definition;
            } elseif ($attributeInput->definitionId) {
                if (self::TAGS !== $attributeInput->definitionId) {
                    $definition = $this->getAttributeDefinition($workspaceId, $attributeInput->definitionId);
                } else {
                    $definition = self::TAGS;
                }
            } elseif ($attributeInput->name) {
                $definition = $this->getAttributeDefinitionBySlug($workspaceId, $attributeInput->name);
            }

            if ($attributeInput instanceof AttributeActionInput) {
                if ($attributeInput->id) {
                    try {
                        $attribute = $this->attributeRepository->find($attributeInput->id);
                        if (!$attribute instanceof Attribute) {
                            throw new BadRequestHttpException(sprintf('Attribute "%s" not found in %s #%d', $attributeInput->id, $contextName, $i));
                        }

                        $definition = $attribute->getDefinition();
                    } catch (ConversionException $e) {
                        throw new BadRequestHttpException(sprintf('Invalid attribute ID "%s" in %s #%d', $attributeInput->id, $contextName, $i), $e);
                    }
                }
            }

            if (self::TAGS === $definition) {
                continue;
            }

            if (!$definition) {
                throw new BadRequestHttpException(sprintf('Missing attribute definition in %s #%d', $contextName, $i));
            }

            $this->denyUnlessGranted($definition);

            $validationContext->setNode($attributeInput->value, $attributeInput, null, sprintf('%s[%s]', $validationContext->getRoot(), $i));

            $this->doValidateAttribute($definition, $attributeInput, $validationContext);
        }

        if ($validationContext->getViolations()->count() > 0) {
            throw new ValidationException($validationContext->getViolations());
        }
    }

    private function denyUnlessGranted(AttributeDefinition $definition): void
    {
        if (!$definition->getPolicy()->isEditable()
            && !$this->security->isGranted(PermissionInterface::EDIT, $definition->getPolicy())) {
            throw new AccessDeniedHttpException(sprintf('Unauthorized to edit attribute definition %s', $definition->getId()));
        }
    }

    private function getAttributeDefinitionBySlug(string $workspaceId, string $slug): AttributeDefinition
    {
        return $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($workspaceId, $slug)
            ?? throw new BadRequestHttpException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $slug, $workspaceId));
    }

    private function getAttributeDefinition(string $workspaceId, string $id): AttributeDefinition
    {
        $def = $this->attributeDefinitionRepository->find($id);
        if (!$def instanceof AttributeDefinition) {
            throw new BadRequestHttpException(sprintf('Attribute definition "%s" not found', $id));
        }
        if ($workspaceId !== $def->getWorkspaceId()) {
            throw new BadRequestHttpException(sprintf('Attribute definition "%s" is not in the same workspace as the asset', $id));
        }

        return $def;
    }
}
