<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class IdAttributeType extends KeywordAttributeType
{
    public const string NAME = 'id';

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function supportsSuggest(): bool
    {
        return false;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        parent::validate($value, $context);

        if ($value && str_contains($value, ' ')) {
            $context->addViolation('ID cannot contain spaces');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
