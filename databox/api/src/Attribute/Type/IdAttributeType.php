<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class IdAttributeType extends KeywordAttributeType
{
    public const string NAME = 'id';

    #[\Override]
    public function isLocaleAware(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return false;
    }

    #[\Override]
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

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }
}
