<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Builds the output DTO of a resource.
 *
 * Mappers are indexed by the output class they build (#[AsTaggedItem(index: XOutput::class)]).
 */
#[AutoconfigureTag(self::TAG)]
interface OutputMapperInterface
{
    final public const string TAG = 'app.output_mapper';

    public function supports(object $data): bool;

    /**
     * @param array<string, mixed> $context the normalization context (groups, user, request URI...)
     */
    public function map(object $data, array $context = []): object;
}
