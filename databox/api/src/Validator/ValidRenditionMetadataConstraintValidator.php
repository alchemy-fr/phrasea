<?php

declare(strict_types=1);

namespace App\Validator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Service\Metadata\WritableMetadataPolicy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidRenditionMetadataConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MetadataManipulator $metadataManipulator,
        private readonly WritableMetadataPolicy $writableMetadataPolicy,
    ) {
    }

    /**
     * @param array<string, mixed>|null        $value
     * @param ValidRenditionMetadataConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRenditionMetadataConstraint) {
            throw new UnexpectedTypeException($constraint, ValidRenditionMetadataConstraint::class);
        }

        if (empty($value)) {
            return;
        }

        foreach ($value as $tagGroupId => $tagValue) {
            if (!is_string($tagGroupId) || !str_contains($tagGroupId, ':')) {
                $this->addViolation($constraint->invalidTagMessage, (string) $tagGroupId);
                continue;
            }

            try {
                $tagGroup = $this->metadataManipulator->createTagGroup($tagGroupId);
            } catch (\Throwable) {
                $this->addViolation($constraint->unknownTagMessage, $tagGroupId);
                continue;
            }

            if (!$this->writableMetadataPolicy->isWritable($tagGroup->getId())) {
                $this->addViolation($constraint->forbiddenTagMessage, $tagGroupId);
                continue;
            }

            if (!$tagGroup->isWritable()) {
                $this->addViolation($constraint->notWritableTagMessage, $tagGroupId);
                continue;
            }

            if (!is_scalar($tagValue)) {
                $this->addViolation($constraint->invalidValueMessage, $tagGroupId);
            }
        }
    }

    private function addViolation(string $message, string $tag): void
    {
        $this->context
            ->buildViolation($message)
            ->setParameter('{{ tag }}', $tag)
            ->addViolation();
    }
}
