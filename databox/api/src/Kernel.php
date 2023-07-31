<?php

namespace App;

use App\DependencyInjection\Compiler\FixApiPlatformPass;
use App\DependencyInjection\Compiler\RemoveUnwantedAutoWiredServicesPass;
use App\DependencyInjection\Compiler\SearchIndexPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

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

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new SearchIndexPass());
        $container->addCompilerPass(new RemoveUnwantedAutoWiredServicesPass());
        $container->addCompilerPass(new FixApiPlatformPass());
    }
}
