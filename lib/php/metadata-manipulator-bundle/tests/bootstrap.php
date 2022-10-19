<?php

use PHPExiftool\InformationDumper;
use PHPExiftool\PHPExiftool;

require dirname(__DIR__).'/vendor/autoload.php';

// build the phpexiftool classes (taggroups)
if (!PHPExiftool::isClassesGenerated()) {
    PHPExiftool::generateClasses([InformationDumper::LISTOPTION_MWG], ['en']);
}
