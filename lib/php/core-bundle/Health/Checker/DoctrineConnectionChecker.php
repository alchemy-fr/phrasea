<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use Doctrine\Persistence\ConnectionRegistry;

final readonly class DoctrineConnectionChecker implements HealthCheckerInterface
{
    public function __construct(private ConnectionRegistry $connectionRegistry, private ?string $connectionName = null)
    {
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
