<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractServiceContainerMigration extends AbstractMigration
{
    protected ?ContainerInterface $container;

    #[Required]
    public function setContainer(?ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    private array $services = [];

    public function setService(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    public function getService(string $name): object
    {
        return $this->services[$name];
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }
}
