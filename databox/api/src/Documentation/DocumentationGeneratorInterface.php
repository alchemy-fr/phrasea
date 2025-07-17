<?php

declare(strict_types=1);

namespace App\Documentation;

interface DocumentationGeneratorInterface
{
    final public const string TAG = 'documentation_generator';

    public function getName(): string;
}
