<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Applies an input DTO to the entity it creates or updates.
 *
 * Mappers are indexed by the input class they handle (#[AsTaggedItem(index: XInput::class)]).
 */
#[AutoconfigureTag(self::TAG)]
interface InputMapperInterface
{
    final public const string TAG = 'app.input_mapper';

    /**
     * @param object|null          $target  the entity to update, null to create a new one
     * @param array<string, mixed> $context the processor context (request, operation...)
     *
     * @return object|null the entity to persist, null when the input removed it
     */
    public function map(object $data, ?object $target, array $context = []): ?object;
}
