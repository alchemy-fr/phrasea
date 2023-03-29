<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Health\Checker;

use Alchemy\WorkflowBundle\Health\HealthCheckerInterface;
use Predis\Client;

class PredisConnectionChecker implements HealthCheckerInterface
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function check(): bool
    {
        $this->client->ping('');

        return true;
    }

    public function getAdditionalInfo(): ?array
    {
        return null;
    }
}
