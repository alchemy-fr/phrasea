<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\CoreBundle\Documentation\DocumentationGenerator;
use Alchemy\RenditionFactory\RenditionBuilderTransformersDocumentation;

final class RenditionBuilderTransformersGenerator extends DocumentationGenerator
{
    public function __construct(private readonly RenditionBuilderTransformersDocumentation $documentation)
    {
    }

    public function getPath(): string
    {
        return '_rendition_transformers.md';
    }

    public function getContent(): string
    {
        return $this->documentation->generate();
    }
}
