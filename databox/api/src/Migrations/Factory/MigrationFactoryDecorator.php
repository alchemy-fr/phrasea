<?php
declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Migrations\AbstractServiceContainerMigration;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationFactoryDecorator implements MigrationFactory
{
    private MigrationFactory $migrationFactory;
    private ContainerInterface $container;

    public function __construct(
        MigrationFactory $migrationFactory,
        ContainerInterface $container
    )
    {
        $this->migrationFactory = $migrationFactory;
        $this->container = $container;
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        if ($instance instanceof ContainerAwareInterface) {
            $instance->setContainer($this->container);
        }

        if ($instance instanceof AbstractServiceContainerMigration) {
        }

        return $instance;
    }
}
