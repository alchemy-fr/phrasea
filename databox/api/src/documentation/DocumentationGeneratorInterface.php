<?php

declare(strict_types=1);

namespace App\documentation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface DocumentationGeneratorInterface
{
    final public const TAG = 'documentation_generator';

    public static function getName(): string;

    public function generate(): string;
}
