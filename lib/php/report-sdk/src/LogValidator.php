<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

readonly class LogValidator
{
    private object $schema;

    public function __construct(
        ?string $schema = null,
        private Validator $validator = new Validator(),
    ) {
        $this->schema = json_decode($schema ?? file_get_contents(__DIR__.'/log-schema.json'), flags: JSON_THROW_ON_ERROR);
    }

    public function validate(array $data): array
    {
        $result = $this->validator->validate(Helper::toJSON($data), $this->schema);

        if ($result->isValid()) {
            return $data;
        }

        $error = $result->error();
        throw new InvalidLogException('Invalid log: '.json_encode((new ErrorFormatter())->format($error, false), flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
