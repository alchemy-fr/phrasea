<?php

namespace Alchemy\MetadataManipulatorBundle\DependencyInjection\Compiler;

use Alchemy\MetadataManipulatorBundle\Exception\BadConfigurationException;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\InformationDumper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuildPhpExiftoolClassesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $dir = $container->getParameter('alchemy_mm.classes_directory');
        $container->getParameterBag()->remove('alchemy_mm.classes_directory');
        @mkdir($dir, 0755, true);
        if(!is_dir($dir) || !is_writable($dir)) {
            throw new BadConfigurationException(sprintf('Cannot access/create classes_directory "%s".', $dir));
        }

        /** @var MetadataManipulator $mm */
        $mm = $container->get(MetadataManipulator::class);

        if (!file_exists($mm->getClassesDirectory() . '/TagGroup/Helper.php')) {
            $mm->getPhpExifTool()->generateClasses([InformationDumper::LISTOPTION_MWG]);
        }
    }
}
