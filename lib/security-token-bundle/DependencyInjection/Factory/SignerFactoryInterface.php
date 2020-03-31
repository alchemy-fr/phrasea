<?php

declare(strict_types=1);

namespace Alchemy\SecurityTokenBundle\DependencyInjection\Factory;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface SignerFactoryInterface
{
    public function getKey(): string;
    public function create(ContainerBuilder $container, $id, array $config): void;
    public function addConfiguration(ArrayNodeDefinition $builder): void;
}
