<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\Validator;

class LogValidator
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var Validator
     */
    private $validator;

    public function __construct(?string $schema = null)
    {
        $this->schema = Schema::fromJsonString($schema ?? file_get_contents(__DIR__.'/log-schema.json'));
        $this->validator = new Validator();
    }

    public function validate(array $data): array
    {
        $result = $this->validator->schemaValidation(\GuzzleHttp\json_decode(\GuzzleHttp\json_encode($data)), $this->schema);

        if ($result->isValid()) {
            return $data;
        } else {
            /** @var ValidationError $error */
            $error = $result->getFirstError();
            throw new InvalidLogException('Invalid log: '.$error->keyword().' '.json_encode($error->keywordArgs(), JSON_PRETTY_PRINT));
        }
    }
}
