<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

/**
 * The values and functions of a script condition. The values are passed as script parameters
 * rather than written in its source: Elasticsearch compiles a script once per distinct source,
 * and the compilations are rate limited for the whole cluster. Conditions of the same shape then
 * share a single compilation. The Painless functions the script calls are declared at its top.
 */
final class ScriptParams
{
    private array $values = [];

    /**
     * @var array<string, string>
     */
    private array $declarations = [];

    /**
     * @return string the reference to the value in the script source
     */
    public function add(mixed $value): string
    {
        $name = 'v'.count($this->values);
        $this->values[$name] = $value;

        return 'params.'.$name;
    }

    public function all(): array
    {
        return $this->values;
    }

    public function addDeclaration(string $name, string $declaration): void
    {
        $this->declarations[$name] ??= $declaration;
    }

    /**
     * @return list<string>
     */
    public function getDeclarations(): array
    {
        return array_values($this->declarations);
    }
}
