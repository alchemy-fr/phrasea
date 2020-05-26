<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use Doctrine\Persistence\ConnectionRegistry;

class DoctrineConnectionChecker implements HealthCheckerInterface
{
    private ConnectionRegistry $connectionRegistry;
    private ?string $connectionName;

    public function __construct(ConnectionRegistry $connectionRegistry, ?string $connectionName = null)
    {
        $this->connectionRegistry = $connectionRegistry;
        $this->connectionName = $connectionName;
    }

    public function getName(): string
    {
        return 'doctrine_dbal';
    }

    public function check(): bool
    {
        return $this->connectionRegistry->getConnection($this->connectionName)->ping();
    }

    public function getAdditionalInfo(): ?array
    {
        return null;
    }
}
