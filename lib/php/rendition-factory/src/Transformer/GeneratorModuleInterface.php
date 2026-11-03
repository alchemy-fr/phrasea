<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;

/**
 * First module of a "generate" build: it produces a file without any source,
 * from the input files of the context (TransformationContextInterface::getInputFiles()).
 */
interface GeneratorModuleInterface
{
    final public const string TAG = 'alchemy_rendition_factory.generator_module';

    public static function getName(): string;

    public function getDocumentation(): Documentation;

    /**
     * @return int how many input files the generator uses at most
     */
    public function getInputCount(array $options): int;

    public function generate(array $options, TransformationContextInterface $context): OutputFileInterface;
}
