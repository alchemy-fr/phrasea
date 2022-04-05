<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Annotation\IgnoreAutowire;
use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveUnwantedAutoWiredServicesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $annotationReader = new AnnotationReader();
        $definitions = $container->getDefinitions();
        foreach ($definitions as $fqcn => $definition) {
            if (substr($fqcn, 0, 4) === 'App\\') {
                try {
                    $refl = new \ReflectionClass($fqcn);
                    $result = $annotationReader->getClassAnnotation($refl, IgnoreAutowire::class);
                    if ($result !== null) {
                        $container->removeDefinition($fqcn);
                    }
                } catch (Exception $e) {
                    // Ignore
                }
            }
        }
    }
}
