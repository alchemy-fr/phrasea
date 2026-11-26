<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;

interface TransformerModuleInterface
{
    final public const string TAG = 'alchemy_rendition_factory.transformer_module';

    /**
     * Highest "timeout" (in seconds) a rendition definition may set on a module, unless configured otherwise:
     * the rendition definitions are written by editors, the workers are shared.
     */
    final public const int DEFAULT_MAX_TIMEOUT = 3600;

    public static function getName(): string;

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface;

    public function getDocumentation(): Documentation;
}
