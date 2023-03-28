<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\InformationDumper;
use PHPExiftool\PHPExiftool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPExiftool\Driver\TagGroup\ExifTool\ExifToolVersion;

class BuildPhpExiftoolClassesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if(!class_exists(ExifToolVersion::class)) {
            PHPExiftool::generateClasses([InformationDumper::LISTOPTION_MWG], ['en'], __DIR__ . "/../../../var/cache/phpexiftool");
        }
    }
}
