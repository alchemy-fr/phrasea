<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\Validator;

readonly class LogValidator
{
    private Schema $schema;

    public function __construct(
        ?string $schema = null,
        private Validator $validator = new Validator(),
    ) {
        $this->schema = Schema::fromJsonString($schema ?? file_get_contents(__DIR__.'/log-schema.json'));
    }

    public function validate(array $data): array
    {
        $result = $this->validator->schemaValidation(json_decode(json_encode($data, flags: JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR), $this->schema);

        if ($result->isValid()) {
            return $data;
        } else {
            /** @var ValidationError $error */
            $error = $result->getFirstError();
            throw new InvalidLogException('Invalid log: '.$error->keyword().' '.json_encode($error->keywordArgs(), flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }
}
