<?php

declare(strict_types=1);

namespace App;

use Alchemy\ConfiguratorBundle\StackConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $config = StackConfig::getConfig();

        $appConfig = $config['uploader'] ?? [];
        $container->setParameter('app.upload.max_file_size', $appConfig['max_upload_file_size'] ?? null);
        $container->setParameter('app.upload.max_commit_size', $appConfig['max_upload_commit_size'] ?? null);
        $container->setParameter('app.upload.max_file_count', $appConfig['max_upload_file_count'] ?? null);
    }
}
