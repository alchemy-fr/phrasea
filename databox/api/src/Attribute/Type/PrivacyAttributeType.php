<?php

declare(strict_types=1);

namespace App\Attribute\Type;

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

    public function validate(mixed $value): ?array
    {
        $errors = parent::validate($value);

        if (!empty($errors)) {
            return $errors;
        }

        $v = (string) $value;
        if ($v && !is_numeric($v)) {
            $errors[] = 'Invalid privacy value';

            return $errors;
        }

        return null;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
