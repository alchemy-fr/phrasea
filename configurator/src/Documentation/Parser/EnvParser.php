<?php

namespace App\Documentation\Parser;

use App\Documentation\Parser\Dto\EnvVar;

final readonly class EnvParser
{
    public function parse(string $envContent): array
    {
        preg_match_all('/^((?:#.+\n\r?)*)(([A-Z0-9_]+)(?:=(.*?))?\n\r?)+$/m', $envContent, $lines);

        $keywords = [
            'category' => 'string',
            'tag' => 'string',
            'allow-empty' => 'string',
            'change-me' => 'string',
            'raw-secret' => 'string',
        ];

        $envVars = [];
        foreach ($lines[0] as $index => $fullMatch) {
            dump($fullMatch);
            $commentBlock = trim($lines[1][$index] ?? '');
            $name = $lines[2][$index];
            $defaultValue = '' !== $lines[3][$index] ? $lines[3][$index] : null;
            $description = null;

            if ($commentBlock) {
                $comments = explode("\n", $commentBlock);
                $descriptionLines = [];
                $info = [];

                foreach ($comments as $comment) {
                    $comment = trim($comment, "# \n");

                    foreach ($keywords as $key => $type) {
                        if (str_starts_with($comment, '@'.$key)) {
                            $info[$key] = trim(substr($comment, strlen($key) + 1));
                            if ('bool' === $type) {
                                $info[$key] = filter_var($info[$key], FILTER_VALIDATE_BOOLEAN);
                            }
                            continue 2;
                        }
                    }

                    // General comment line
                    $descriptionLines[] = trim($comment);
                }
                $description = implode(' ', $descriptionLines);
            }

            $envVars[] = new EnvVar(
                name: $name,
                defaultValue: $defaultValue,
                description: $description,
                category: $info['category'] ?? null,
                allowEmpty: $info['allow-empty'] ?? null,
                changeMe: $info['change-me'] ?? false,
                tags: $info['tags'] ?? [],
                rawSecret: $info['raw-secret'] ?? null,
            );
        }

        return $envVars;
    }
}
