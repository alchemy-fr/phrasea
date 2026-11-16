<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

/**
 * The values of a script condition, passed as script parameters rather than written in its source:
 * Elasticsearch compiles a script once per distinct source, and the compilations are rate limited
 * for the whole cluster. Conditions of the same shape then share a single compilation.
 */
final class ScriptParams
{
    private array $values = [];

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
}
