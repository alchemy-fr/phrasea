<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;

final readonly class RabbitMQConnectionChecker implements HealthCheckerInterface
{
    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function check(): bool
    {
        // TODO
        return true;
    }

    public function getAdditionalInfo(): ?array
    {
        return null;
    }
}
