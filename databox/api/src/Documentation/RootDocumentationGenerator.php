<?php

declare(strict_types=1);

namespace App\Documentation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(DocumentationGeneratorInterface::TAG)]
class RootDocumentationGenerator extends DocumentationGenerator
{
    public function __construct(
        private InitialValuesDocumentationGenerator $initialValuesDocumentationGenerator,
        private RenditionBuilderDocumentationGenerator $renditionBuilderDocumentationGenerator)
    {
    }

    public function getName(): string
    {
        return 'root';
    }

    public function getTitle(): string
    {
        return 'Phrasea documentation';
    }

    public function getChildren(): array
    {
        return [
            $this->initialValuesDocumentationGenerator,
            $this->renditionBuilderDocumentationGenerator,
        ];
    }
}
