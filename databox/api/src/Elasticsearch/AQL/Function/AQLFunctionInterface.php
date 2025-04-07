<?php

namespace App\Elasticsearch\AQL\Function;

interface AQLFunctionInterface
{
    public function resolve(array $arguments): mixed;

    public function getScript(array $arguments): string;

    public static function getName(): string;

    public static function getArguments(): array;
}
