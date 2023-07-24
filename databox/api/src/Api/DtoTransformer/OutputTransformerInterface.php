<?php

declare(strict_types=1);

namespace App\Api\DtoTransformer;


use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api.output_transformer')]
interface OutputTransformerInterface
{
    public function supports(string $outputClass, object $data): bool;

    public function transform(object $data, string $outputClass, array $context = []): object;
}
