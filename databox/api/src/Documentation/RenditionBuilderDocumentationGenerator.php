<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;

class RenditionBuilderDocumentationGenerator implements DocumentationGeneratorInterface
{
    public function __construct(private RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation)
    {
    }

    public static function getName(): string
    {
        return 'Rendition Factory';
    }

    public function generate(): string
    {
        return $this->renditionBuilderConfigurationDocumentation->generate();
    }
}
