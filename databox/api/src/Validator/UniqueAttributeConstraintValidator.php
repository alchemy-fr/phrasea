<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\Attribute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueAttributeConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param Attribute               $value
     * @param SameWorkspaceConstraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        $definition = $value->getDefinition();
        $asset = $value->getAsset();
        if (null === $definition || null === $asset) {
            return;
        }

        if ($definition->isMultiple()) {
            return;
        }

        $duplicates = $this->em
            ->getRepository(Attribute::class)
            ->getDuplicates($value);

        if (!empty($duplicates)) {
            $this->context
                ->buildViolation(sprintf(
                    'Attribute "%s" already exists for asset "%s" in workspace "%s"',
                    $definition->getName(),
                    $asset->getId(),
                    $definition->getWorkspaceId()
                ))
                ->addViolation();
        }
    }
}
