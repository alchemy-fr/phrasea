<?php

declare(strict_types=1);

namespace App\Api\DTO\Input;

use App\Form\Builder\FormDefinition;
use App\Form\Builder\InvalidFormDefinitionException;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TargetFormInput
{
    public const string GROUP = 'target:form:w';

    /**
     * A definition of the form builder, null to remove the form.
     */
    #[Groups([self::GROUP])]
    public ?array $form = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null === $this->form) {
            return;
        }

        try {
            FormDefinition::fromArray($this->form);
        } catch (InvalidFormDefinitionException $e) {
            $context->buildViolation($e->getMessage())
                ->atPath('form'.($e->path ? '.'.$e->path : ''))
                ->addViolation();
        }
    }
}
