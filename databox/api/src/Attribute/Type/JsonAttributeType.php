<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class JsonAttributeType extends CodeAttributeType
{
    public const string NAME = 'json';

    #[\Override]
    public function validate(mixed $value): ?array
    {
        $errors = parent::validate($value);
        if (!empty($errors)) {
            return $errors;
        }

        try {
            json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['Invalid JSON: '.$e->getMessage()];
        }

        return null;
    }
}
