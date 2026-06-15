<?php

declare(strict_types=1);

namespace App\Attribute\Type;

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

    public function validate(mixed $value): ?array
    {
        $errors = parent::validate($value);

        if (!empty($errors)) {
            return $errors;
        }

        $v = (string) $value;
        if ($v && str_contains($v, ' ')) {
            $errors[] = 'ID cannot contain spaces';

            return $errors;
        }

        return null;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
