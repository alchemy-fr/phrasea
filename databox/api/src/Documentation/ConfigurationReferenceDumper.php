<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\RenditionFactory\Transformer\Documentation;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;

/**
 * Dumps configuration trees as plain YAML references suitable for the client "Configuration reference" panels.
 */
final class ConfigurationReferenceDumper
{
    public function dumpTree(TreeBuilder $treeBuilder): string
    {
        $node = $treeBuilder->buildTree();
        $dumper = new YamlReferenceDumper();

        $output = (string) $dumper->dumpNode($node);
        // Drop the artificial "root" node line (comments dumped above it are preserved)
        $output = preg_replace("#^root:(\s+\[])?\s*$#m", '', $output);
        // Dedent the level introduced by the root node
        $output = preg_replace('#^ {4}#m', '', (string) $output);
        $output = preg_replace("#\n{2,}#", "\n", (string) $output);

        return trim((string) $output);
    }

    /**
     * Renders a Documentation as a YAML reference: the configuration tree as plain YAML,
     * the footer as YAML comments, children appended recursively (with their headers as comments).
     * The root header is skipped by default, as it is meant to be displayed separately as a description.
     */
    public function dumpDocumentation(Documentation $documentation, bool $withHeader = false): string
    {
        $parts = [];

        if ($withHeader && '' !== trim($documentation->getHeader())) {
            $parts[] = self::commentOut(trim($documentation->getHeader()));
        }

        $parts[] = $this->dumpTree($documentation->getTreeBuilder());

        if ('' !== trim($documentation->getFooter())) {
            $parts[] = self::commentOut(trim($documentation->getFooter()));
        }

        foreach ($documentation->getChildren() as $child) {
            $parts[] = $this->dumpDocumentation($child, true);
        }

        return implode("\n", array_filter($parts, fn (string $part): bool => '' !== $part));
    }

    /**
     * Prefixes every line with "# " (lines already commented are left untouched).
     */
    private static function commentOut(string $text): string
    {
        return implode("\n", array_map(
            function (string $line): string {
                if ('' === trim($line)) {
                    return '#';
                }
                if (str_starts_with(ltrim($line), '#')) {
                    return $line;
                }

                return '# '.$line;
            },
            explode("\n", $text)
        ));
    }
}
