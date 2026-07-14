<?php

declare(strict_types=1);

namespace App\Migrations\Factory;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;


#[AsDecorator('doctrine.migrations.migrations_factory')]
class MigrationFactoryDecorator implements MigrationFactory
{
    public function __construct(
        private readonly MigrationFactory $migrationFactory,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container
    )
    {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        if (method_exists($instance, 'setContainer')) {
            $instance->setContainer($this->container);
        }

        return $instance;
    }
}
