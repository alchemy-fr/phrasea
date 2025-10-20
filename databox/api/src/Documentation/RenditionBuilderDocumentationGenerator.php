<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;

class RenditionBuilderDocumentationGenerator extends DocumentationGenerator
{
    public function __construct(private RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation)
    {
    }

    public function getPath(): string
    {
        return 'doc/_rendition_factory.md';
    }

    public function getTitle(): string
    {
        return 'Rendition Factory';
    }

    public function getContent(): string
    {
        return $this->renditionBuilderConfigurationDocumentation->generate();
    }
}
