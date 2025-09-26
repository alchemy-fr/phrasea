<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface InputTransformerInterface
{
    final public const string TAG = 'api.input_transformer';

    public function supports(string $resourceClass, object $data): bool;

    public function transform(object $data, string $resourceClass, array $context = []): object|iterable;
}
