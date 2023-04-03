<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\InformationDumper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class BuildPhpExiftoolClassesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        /** @var MetadataManipulator $mm */
        $mm = $container->get('alchemy.metadatamanipulator');
        if(!file_exists($mm->getClassesDirectory() . '/TagGroup/Helper.php')) {
            $mm->getPhpExifTool()->generateClasses([InformationDumper::LISTOPTION_MWG], ['en']);
        }
    }
}
