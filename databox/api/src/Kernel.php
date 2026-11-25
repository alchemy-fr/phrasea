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
     */
    protected function initializeContainer(): void
    {
        if (!$this->debug && 'prod' !== $this->environment) {
            $cache = new ConfigCache($this->getBuildDir().'/'.$this->getContainerClass().'.php', true);
            if (is_file($cache->getPath()) && !$cache->isFresh()) {
                @unlink($cache->getPath());
            }
        }

        parent::initializeContainer();
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
