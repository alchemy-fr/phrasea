<?php

declare(strict_types=1);

namespace App\Form\Builder\Constraint;

use App\Databox\DataboxCatalog;
use App\Databox\DataboxException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class DataboxValuesValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DataboxCatalog $catalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DataboxValues) {
            throw new UnexpectedTypeException($constraint, DataboxValues::class);
        }

        $ids = array_values(array_unique(array_filter(
            is_array($value) ? $value : [$value],
            // Malformed values are reported by the Uuid constraint
            static fn (mixed $id): bool => is_string($id) && Uuid::isValid($id),
        )));
        if ([] === $ids) {
            return;
        }

        try {
            $missing = null !== $constraint->entityListId
                ? $this->catalog->findMissingEntities($constraint->entityListId, $ids)
                : $this->catalog->findMissingTags((string) $constraint->workspaceId, $ids);
        } catch (DataboxException $e) {
            $this->logger->warning('Form values could not be checked against Databox: '.$e->getMessage());
            $this->context->buildViolation($constraint->unavailableMessage)->addViolation();

            return;
        }

        foreach ($missing as $id) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($id))
                ->addViolation();
        }
    }
}
