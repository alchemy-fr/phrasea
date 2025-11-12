<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Documentation;

abstract class DocumentationGenerator implements DocumentationGeneratorInterface
{
    private array $levels = [];

    final public function setLevels(array $levels): void
    {
        $this->levels = $levels;
    }

    final public function getLevels(): array
    {
        return $this->levels;
    }

    public function getHeader(): ?string
    {
        return null;
    }

    public function getContent(): ?string
    {
        return null;
    }

    public function getFooter(): ?string
    {
        return null;
    }

    public function getChildren(): array
    {
        return [];
    }
}
