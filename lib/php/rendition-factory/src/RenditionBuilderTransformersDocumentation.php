<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class RenditionBuilderTransformersDocumentation
{
    public function __construct(
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $transformers,
    ) {
    }

    public function generate(): string
    {
        $text = '';
        foreach ($this->transformers->getProvidedServices() as $transformerName => $transformerFqcn) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformerName);
            $text .= $this->getTransformerDocumentation($transformerName, $transformer);
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

        return "### $transformerName\n".$docToText($documentation);
    }
}
