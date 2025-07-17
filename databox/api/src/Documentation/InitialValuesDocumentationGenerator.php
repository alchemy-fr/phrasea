<?php

declare(strict_types=1);

namespace App\Documentation;

use Symfony\Component\Yaml\Yaml;

class InitialValuesDocumentationGenerator extends DocumentationGenerator
{
    public function getName(): string
    {
        return 'initial_attribute_values';
    }

    public function getTitle(): string
    {
        return 'Initial Attribute Values';
    }

    public function getSubdirectory(): string
    {
        return 'Databox/Attributes';
    }

    public function getContent(): ?string
    {
        $n = 0;
        $output = '';
        foreach (Yaml::parseFile(__DIR__.'/InitialAttributeValuesResolverData.yaml') as $example) {
            if (!($example['documentation'] ?? false)) {
                continue;
            }

            if ($n++ > 0) {
                $output .= "\n---\n";
            }

            $levels = $this->getLevels();
            $levels[] = $n;

            $output .= sprintf("## %s: %s\n",
                join('.', $levels),
                $example['documentation']['title'] ?? ''
            );
            if ($description = $example['documentation']['description'] ?? '') {
                $output .= sprintf("%s\n", $description);
            }

            $output .= "### Attribute(s) definition(s) ...\n";
            foreach ($example['definitions'] as $name => $definition) {
                $output .= sprintf("- __%s__:   `%s` ; [%s] `multi` ; [%s] `translatable`\n",
                    $name,
                    $definition['fieldType'] ?? 'text',
                    $definition['isMultiple'] ?? false ? 'X' : ' ',
                    $definition['isTranslatable'] ?? false ? 'X' : ' '
                );

                $output .= "\n";

                if (is_array($definition['initialValues'] ?? null)) {
                    foreach ($definition['initialValues'] as $locale => $initializer) {
                        $output .= sprintf("  - locale `%s`\n", $locale);
                        $code = json_encode(json_decode($initializer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $this->codeBlockIndented($output, $code, 'json', 1);
                    }
                } elseif (null !== ($definition['initialValues'] ?? null)) {
                    $initializer = $definition['initialValues'];
                    $code = json_encode(json_decode($initializer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $this->codeBlockIndented($output, $code, 'json', 0);
                }

                $output .= "\n";

                $output .= "### ... with file metadata ...\n";
                $output .= "| metadata | value(s) |\n";
                $output .= "|---|---|\n";
                foreach ($example['metadata'] ?? [] as $metadataName => $values) {
                    $v = is_array($values) ? $values : [$values];
                    $output .= sprintf("| %s | `%s` |\n", $metadataName, join('` ; `', $v));
                }

                $output .= "\n";

                $output .= "### ... set attribute(s) initial value(s)\n";
                $this->dumpExpected($output, $example['expected'], 1);

                $output .= "\n";
            }
        }

        return $output;
    }

    private function codeBlockIndented(string &$output, string $code, string $language, int $indent = 0): void
    {
        $tab = str_repeat('  ', $indent);
        $output .= sprintf("%s```%s\n", $tab, $language);
        foreach (explode("\n", $code) as $line) {
            $output .= sprintf("%s%s\n", $tab, $line);
        }
        $output .= sprintf("%s```\n", $tab);
    }

    private function dumpExpected(string &$output, array $expected, int $indent = 0): void
    {
        $tab = str_repeat('  ', $indent);
        $output .= sprintf("%s| Attributes | initial value(s) |\n", $tab);
        $output .= sprintf("%s|---|---|\n", $tab);
        foreach ($expected as $attributeName => $value) {
            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    // a simple array of values
                    $n = 1;
                    foreach ($value as $v) {
                        $output .= sprintf("%s| %s #%d | `%s` |\n", $tab, $attributeName, $n++, $v);
                    }
                } else {
                    // an array with key=locale
                    foreach ($value as $locale => $v) {
                        $output .= sprintf("%s| __locale `%s`__ |   |\n", $tab, $locale);
                        if (is_array($v)) {
                            // an array of values
                            foreach ($v as $n => $w) {
                                $output .= sprintf("%s| %s #%d | `%s` |\n", $tab, $attributeName, $n + 1, $w);
                            }
                        } else {
                            $output .= sprintf("%s| %s  | `%s` |\n", $tab, $attributeName, $v);
                        }
                    }
                }
            } else {
                // a single value
                $output .= sprintf("%s| %s | `%s`\n", $tab, $attributeName, $value);
            }
        }
    }

    private function isNumericArray($a): bool
    {
        if (!is_array($a)) {
            return false;
        }
        foreach ($a as $k => $v) {
            if (!is_numeric($k)) {
                return false;
            }
        }

        return true;
    }
}
