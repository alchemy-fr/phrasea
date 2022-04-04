<?php

namespace App\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

abstract class AbstractServiceContainerMigration extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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
