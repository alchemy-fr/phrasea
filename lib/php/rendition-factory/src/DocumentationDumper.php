<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class DocumentationDumper
{
    public function __construct(
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $transformers,
        #[TaggedLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')]
        private ServiceLocator $formats,
    ) {
    }

    public static function getName(): string
    {
        return 'rendition-factory';
    }

    public function dump(): string
    {
        $text = '';
        foreach ($this->transformers->getProvidedServices() as $transformerName => $transformerFqcn) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformerName);
            $text .= $this->getTransformerDocumentation($transformerName, $transformer);
        }
        $text .= $this->listFormats();

        return $text;
    }

    private function listFormats(): string
    {
        $formats = [];
        foreach ($this->formats->getProvidedServices() as $formatName => $formatFqcn) {
            /** @var FormatInterface $format */
            $format = $this->formats->get($formatName);
            $family = $format->getFamily()->value;
            if (!array_key_exists($family, $formats)) {
                $formats[$family] = [];
            }
            $formats[$family][] = $format;
        }
        ksort($formats);

        $text = "## Video transformers output `format`s.\n";
        $text .= "| Family | Format | Mime type | Extensions |\n";
        $text .= "|-|-|-|-|\n";
        foreach ($formats as $familyFormats) {
            $text .= sprintf("| %s ||||\n",
                $familyFormats[0]->getFamily()->value,
            );
            foreach ($familyFormats as $format) {
                $text .= sprintf("|| %s | %s | %s |\n",
                    $format->getFormat(),
                    $format->getMimeType(),
                    implode(', ', $format->getAllowedExtensions())
                );
            }
        }

        return $text;
    }

    private function getTransformerDocumentation(string $transformerName, TransformerModuleInterface $transformer): string
    {
        $docToText = function (Documentation $documentation, int $depth = 0) use (&$docToText): string {

            $text = '';
            if ($t = $documentation->getHeader()) {
                $text .= $t."\n";
            }

            $treeBuilder = $documentation->getTreeBuilder();
            $node = $treeBuilder->buildTree();
            $dumper = new YamlReferenceDumper();

            $t = $dumper->dumpNode($node);
            $t = preg_replace("#^root:($|(\s+)\[]$)#m", "-\n", (string) $t);
            $t = preg_replace("#\n+#", "\n", $t);
            $t = trim($t);

            $text .= "```yaml\n".$t."\n```\n";

            if ($t = $documentation->getFooter()) {
                $text .= $t."\n";
            }

            foreach ($documentation->getChildren() as $child) {
                $text .= $docToText($child, $depth + 1);
            }

            return $text;
        };

        $documentation = $transformer->getDocumentation();

        return "## `$transformerName` transformer module\n".$docToText($documentation);
    }
}
