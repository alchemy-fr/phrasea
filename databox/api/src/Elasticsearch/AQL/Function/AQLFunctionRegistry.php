<?php

namespace App\Elasticsearch\AQL\Function;

final class AQLFunctionRegistry
{
    private array $functions = [];

    public function __construct()
    {
        $this->register(new NowFunction());
        $this->register(new DateAddFunction());
        $this->register(new DateSubFunction());
        $this->register(new SubstringFunction());
        $this->register(new ConcatFunction());
    }

    public function register(AQLFunctionInterface $function): void
    {
        $this->functions[$function::getName()] = $function;
    }

    public function getFunction(string $name): ?AQLFunctionInterface
    {
        return $this->functions[$name] ?? null;
    }
}
