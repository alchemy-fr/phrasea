<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        $config = $config['notifier'] ?? [];

        if (isset($config['admin']['logo']['src'])) {
            $siteName = sprintf(
                '<img src="%s" width="%s" />',
                $config['admin']['logo']['src'],
                $config['admin']['logo']['with']
            );
        } else {
            $siteName = 'Notify Admin';
        }
        $container->setParameter('easy_admin.site_name', $siteName);
    }
}
