<?php

declare(strict_types=1);

namespace App\Integration\Expose;

use App\Integration\AbstractIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Validator\Constraints\Url;

class ExposeIntegration extends AbstractIntegration
{
    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${EXPOSE_API_URL}')
                ->cannotBeEmpty()
                ->info('The Expose API base URL')
            ->end()
        ;
    }

    public static function requiresWorkspace(): bool
    {
        return false;
    }

    public function validateConfiguration(array $config): void
    {
        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    public static function getTitle(): string
    {
        return 'Expose';
    }

    public static function getName(): string
    {
        return 'phrasea.expose';
    }
}
