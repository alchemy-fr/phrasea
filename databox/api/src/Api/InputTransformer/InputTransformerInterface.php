<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api.input_transformer')]
interface InputTransformerInterface
{
    public function supports(string $resourceClass, object $data): bool;

    public function transform(object $data, string $resourceClass, array $context = []): object;
}
