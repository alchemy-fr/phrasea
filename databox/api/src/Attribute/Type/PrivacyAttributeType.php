<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PrivacyAttributeType extends KeywordAttributeType
{
    public const string NAME = 'privacy';

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

        if ($context->getViolations()->count() > 0) {
            return;
        }

        $v = (string) $value;
        if ($v && !is_numeric($v)) {
            $context->addViolation('Invalid privacy value');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
