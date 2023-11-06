<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface HealthCheckerInterface
{
    final public const TAG = 'alchemy_core.health_checker';

    public function getName(): string;

    public function check(): bool;

    public function getAdditionalInfo(): ?array;
}
