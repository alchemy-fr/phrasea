<?php

namespace Jq;

class Executor
{
    public function filter(string $filter, int $flags = 0)
    {
    }

    public function variable(string $name, string $value): self
    {
    }

    public function variables(): array
    {
    }
}

class Input
{
    public static function fromString(string $text): Executor
    {
    }

    public static function fromFile(string $file): Executor
    {
    }
}

class Run
{
    public static function fromString(string $text, string $filter, int $flags = 0, array $variables = [])
    {
    }

    public static function fromFile(string $file, string $filter, int $flags = 0, array $variables = [])
    {
    }
}
