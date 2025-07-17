<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;

class RenditionBuilderDocumentationGenerator extends DocumentationGenerator
{
    public function __construct(private RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation)
    {
    }

    public function getName(): string
    {
        return 'rendition_factory';
    }

    public function getTitle(): string
    {
        return 'Rendition Factory';
    }

    public function getSubdirectory(): string
    {
        return 'Databox/Renditions';
    }

    public function getContent(): string
    {
        return $this->renditionBuilderConfigurationDocumentation->generate();
    }
}
