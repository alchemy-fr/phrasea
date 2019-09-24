<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\OAuth\OAuthProviderFactory;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

class AppExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $jsonConfigSrc = '/configs/config.json';
        if (file_exists($jsonConfigSrc)) {
            $config = json_decode(file_get_contents($jsonConfigSrc), true);
            // Add for fresh cache
            $container->addResource(new FileResource($jsonConfigSrc));
        } else {
            $config = [];
        }

        $def = new Definition(OAuthProviderFactory::class);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
        $providers = $config['auth']['oauth_providers'] ?? [];
        $def->setArgument('$oAuthProviders', $providers);
        $container->setDefinition($def->getClass(), $def);

        if (isset($config['admin']['logo']['src'])) {
            $siteName = sprintf(
                '<img src="%s" width="%s" />',
                $config['admin']['logo']['src'],
                $config['admin']['logo']['with']
            );
        } else {
            $siteName = 'Auth Admin';
        }

        $container->setParameter('easy_admin.site_name', $siteName);
        $container->setParameter('available_locales', $config['available_locales']);
    }
}
