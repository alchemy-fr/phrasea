<?php

declare(strict_types=1);

namespace App\Service\Duplicate;

/**
 * Names the copy of an item: "X (copy)", then "X (copy 2)", "X (copy 3)"... until a free one.
 * The copy of a copy restarts from the original name: duplicating "X (copy)" gives "X (copy 2)".
 */
final readonly class CopyNameGenerator
{
    private const string SUFFIX_PATTERN = '/^(.*\S)\s+\(copy(?:\s+\d+)?\)$/u';

    /**
     * @param callable(string): bool $isTaken   whether the candidate name cannot be used
     * @param int|null               $maxLength the max length (in characters) of the name column
     */
    public function generate(string $name, callable $isTaken, ?int $maxLength = null): string
    {
        $base = self::getBaseName($name);

        for ($i = 1;; ++$i) {
            $suffix = 1 === $i ? ' (copy)' : sprintf(' (copy %d)', $i);
            $candidate = $base.$suffix;

            if (null !== $maxLength && mb_strlen($candidate) > $maxLength) {
                $available = max(0, $maxLength - mb_strlen($suffix));
                $candidate = rtrim(mb_substr($base, 0, $available)).$suffix;
            }

            if (!$isTaken($candidate)) {
                return $candidate;
            }
        }
    }

    public static function getBaseName(string $name): string
    {
        $name = trim($name);
        if (1 === preg_match(self::SUFFIX_PATTERN, $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }
}
