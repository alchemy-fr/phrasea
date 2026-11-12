<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DoctrineConnectionChecker implements HealthCheckerInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine')]
        private ConnectionRegistry $connectionRegistry,
        private ?string $connectionName = null,
    ) {
    }

    public function getName(): string
    {
        return 'doctrine_dbal';
    }

    public function check(): bool
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection($this->connectionName);
        $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());

        return true;
    }

    public function getAdditionalInfo(): ?array
    {
        return null;
    }
}
