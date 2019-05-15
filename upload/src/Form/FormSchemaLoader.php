<?php

declare(strict_types=1);

namespace App\Form;

class FormSchemaLoader
{
    public function loadSchema(): array
    {
        $schema = json_decode(file_get_contents(__DIR__ . '/../../config/liform-schema.json'), true);

        return $schema;
    }
}
