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
            $buildDir = $this->getEffectiveBuildDir();
            $containerPath = $buildDir.'/'.$this->getContainerClass().'.php';
            // Checked once per container in a process, as Symfony does in debug
            if (!(self::$freshCache[$containerPath] ?? false)) {
                if (new ConfigCache($containerPath, true, null, self::getSkippedResourceTypes())->isFresh()) {
                    self::$freshCache[$containerPath] = true;
                } else {
                    @unlink($containerPath);
                    @unlink($buildDir.'/'.$this->getContainerClass().'.bundles.php');
                }
            }
        }

        parent::initializeBundles();
    }

    /**
     * The resources left out of the freshness check, as Symfony reads SYMFONY_DISABLE_RESOURCE_TRACKING.
     *
     * @return list<string>|null
     */
    private static function getSkippedResourceTypes(): ?array
    {
        $skip = $_SERVER['SYMFONY_DISABLE_RESOURCE_TRACKING'] ?? '';
        $skip = filter_var($skip, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? explode(',', $skip);

        return \is_array($skip) && ['*'] !== $skip ? $skip : ($skip ? [] : null);
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
