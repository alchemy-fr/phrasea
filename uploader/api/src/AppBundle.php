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
        $container->setParameter('app.upload.max_file_size', StackConfig::generateConfigEnvKey('uploader.max_upload_file_size', ''));
        $container->setParameter('app.upload.max_commit_size', StackConfig::generateConfigEnvKey('uploader.max_upload_commit_size', ''));
        $container->setParameter('app.upload.max_file_count', StackConfig::generateConfigEnvKey('uploader.max_upload_file_count', ''));
    }
}
