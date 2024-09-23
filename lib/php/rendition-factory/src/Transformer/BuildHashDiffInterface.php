<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;

interface BuildHashDiffInterface
{
    public function buildHashesDiffer(array $buildHashes, array $options, TransformationContextInterface $transformationContext): bool;
}
