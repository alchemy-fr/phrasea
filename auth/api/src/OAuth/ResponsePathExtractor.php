<?php

declare(strict_types=1);

namespace App\OAuth;

class ResponsePathExtractor
{
    /**
     * Extracts a value from the response for a given path.
     *
     * @param string $path Name of the path to get the value for
     */
    public static function getValueForPath(array $paths, ?array $data, string $path)
    {
        if (!$data) {
            return null;
        }

        $steps = $paths[$path] ?? null;
        if (!$steps) {
            return null;
        }

        if (is_array($steps)) {
            if (1 === count($steps)) {
                return self::getValue(current($steps), $data);
            }

            $value = [];
            foreach ($steps as $step) {
                $value[] = self::getValue($step, $data);
            }

            return trim(implode(' ', $value)) ?: null;
        }

        return self::getValue($steps, $data);
    }

    /**
     * @return array|string|null
     */
    private static function getValue(string $steps, array $data)
    {
        $value = $data;
        foreach (explode('.', $steps) as $step) {
            if (!array_key_exists($step, $value)) {
                return null;
            }

            $value = $value[$step];
        }

        return $value;
    }
}
