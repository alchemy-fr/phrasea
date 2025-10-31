<?php

declare(strict_types=1);

namespace App\Documentation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(DocumentationGeneratorInterface::TAG)]
interface DocumentationGeneratorInterface
{
    final public const string TAG = 'documentation_generator';

    public function getPath(): string;

    public function setLevels(array $levels): void;

    public function getLevels(): array;

    public function getHeader(): ?string;

    public function getContent(): ?string;

    public function getFooter(): ?string;

    /** return DocumentationGeneratorInterface[] */
    public function getChildren(): array;
}
