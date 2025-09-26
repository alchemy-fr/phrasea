<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface OutputTransformerInterface
{
    final public const string TAG = 'api.output_transformer';

    public function supports(string $outputClass, object $data): bool;

    public function transform(object $data, string $outputClass, array &$context = []): object;
}
