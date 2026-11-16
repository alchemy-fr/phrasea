<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AttributeDefinitionQuotaConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeDefinitionRepository $repository,
        #[Autowire(env: 'int:ATTRIBUTE_DEFINITIONS_MAX_PER_WORKSPACE')]
        private readonly int $maxPerWorkspace,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AttributeDefinitionQuotaConstraint) {
            throw new UnexpectedTypeException($constraint, AttributeDefinitionQuotaConstraint::class);
        }

        // Only a new definition counts against the quota: the existing ones stay editable
        if (!$value instanceof AttributeDefinition || $this->em->contains($value)) {
            return;
        }

        $workspaceId = $value->getWorkspace()?->getId();
        if (null === $workspaceId) {
            return;
        }

        if ($this->repository->countWorkspaceDefinitions($workspaceId) >= $this->maxPerWorkspace) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', (string) $this->maxPerWorkspace)
                ->addViolation();
        }
    }
}
