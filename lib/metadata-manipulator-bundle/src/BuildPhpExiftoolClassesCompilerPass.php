<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\InformationDumper;
use PHPExiftool\PHPExiftool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuildPhpExiftoolClassesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!PHPExiftool::isClassesGenerated()) {
            PHPExiftool::generateClasses([InformationDumper::LISTOPTION_MWG], ['en']);
        }
    }
}
