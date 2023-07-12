<?php

declare(strict_types=1);

namespace App\Api\DtoTransformer;

use ApiPlatform\Metadata\Operation;

interface OutputTransformerInterface
{
    public function supports(string $outputClass, string $dataClass): bool;

    public function transform(object $data, string $outputClass, Operation $operation, array $context = []): object;
}
