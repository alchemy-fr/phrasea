<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Symfony never rebuilds a non-debug container once compiled. Outside prod, the code changes
     * under it: a `--no-debug` run (the Elasticsearch populate, see ESPopulateHandler and the
     * setup scripts) would otherwise boot a container compiled from older sources.
     *
     * The check runs before the bundles are initialized: without debug, they are read from the
     * cached `<Container>.bundles.php` without any freshness check, and a container recompiled
     * from that stale list would dump it again.
     */
    protected function initializeBundles(): void
    {
        if (!$this->debug && 'prod' !== $this->environment) {
            $containerPath = $this->getBuildDir().'/'.$this->getContainerClass().'.php';
            if (!new ConfigCache($containerPath, true)->isFresh()) {
                @unlink($containerPath);
                @unlink($this->getBuildDir().'/'.$this->getContainerClass().'.bundles.php');
            }
        }

        parent::initializeBundles();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/{services}.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } elseif (is_file($path = \dirname(__DIR__).'/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }
    }
}
