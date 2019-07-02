<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\OAuth\OAuthProviderFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AppExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $jsonConfigSrc = '/configs/config.json';
        $config = json_decode(file_get_contents($jsonConfigSrc), true);

        // Add for fresh cache
        $container->addResource(new FileResource($jsonConfigSrc));

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $def = $container->getDefinition(OAuthProviderFactory::class);
        $def->setArgument('$oAuthProviders', $config['auth']['oauth_providers']);

        $logo = $config['admin']['logo'];
        if (isset($logo['src'])) {
            $siteName = sprintf(
                '<img src="%s" width="%s" />',
                $logo['src'],
                $logo['with']
            );
        } else {
            $siteName = 'Auth Admin';
        }
        $container->setParameter('easy_admin.site_name', $siteName);
    }
}
