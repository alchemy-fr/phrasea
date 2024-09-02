<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Module;

use Alchemy\RenditionFactory\File\InputFile;
use Alchemy\RenditionFactory\File\OutputFile;
use Alchemy\RenditionFactory\Transformation\TransformationContext;

interface TransformationModuleInterface {
    public function getDocumentation(): string;

    /** @throw UnsupportedException */
    public function process(InputFile $inputFile, array $options, TransformationContext $context): OutputFile;
}
