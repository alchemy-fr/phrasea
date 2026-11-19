<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL\Function;

interface AQLFunctionInterface
{
    public function resolve(array $arguments): mixed;

    /**
     * @param string[] $arguments the Painless expressions of the arguments
     */
    public function getScript(array $arguments): string;

    /**
     * The Painless function called by {@see self::getScript()}, declared once at the top of the
     * script, or null when it calls none.
     */
    public function getScriptDeclaration(): ?string;

    public static function getName(): string;

    public static function getArguments(): array;
}
