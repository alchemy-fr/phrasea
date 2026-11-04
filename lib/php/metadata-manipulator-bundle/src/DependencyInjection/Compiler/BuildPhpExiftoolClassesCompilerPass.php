<?php

namespace Alchemy\MetadataManipulatorBundle\DependencyInjection\Compiler;

use Alchemy\MetadataManipulatorBundle\Exception\BadConfigurationException;
use PHPExiftool\InformationDumper;
use PHPExiftool\PHPExiftool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuildPhpExiftoolClassesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $dir = $container->getParameter('alchemy_mm.classes_directory');
        $container->getParameterBag()->remove('alchemy_mm.classes_directory');
        @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new BadConfigurationException(sprintf('Cannot access/create classes_directory "%s".', $dir));
        }

        // Built by hand instead of $container->get(MetadataManipulator::class): fetching a
        // service from the ContainerBuilder instantiates a whole branch of the service graph
        // while the container is still being compiled, at a point where "%env(...)%" values
        // are unresolved placeholders. Any service reachable from here (the logger and its
        // Monolog handlers, for instance) would then be forbidden from using env variables.
        $phpExifTool = new PHPExiftool($dir);

        if (!file_exists($phpExifTool->getClassesRootDirectory().'/TagGroup/Helper.php')) {
            $phpExifTool->generateClasses([InformationDumper::LISTOPTION_MWG]);
        }
    }
}
