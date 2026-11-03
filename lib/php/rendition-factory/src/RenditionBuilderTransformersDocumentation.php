<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\GeneratorModuleInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class RenditionBuilderTransformersDocumentation
{
    public function __construct(
        #[AutowireLocator(TransformerModuleInterface::TAG)]
        private ServiceLocator $transformers,
        #[AutowireLocator(GeneratorModuleInterface::TAG)]
        private ServiceLocator $generators,
    ) {
    }

    public function generate(): string
    {
        $text = '';
        foreach ([$this->transformers, $this->generators] as $modules) {
            foreach (array_keys($modules->getProvidedServices()) as $moduleName) {
                /** @var TransformerModuleInterface|GeneratorModuleInterface $module */
                $module = $modules->get($moduleName);
                $text .= $this->getModuleDocumentation($moduleName, $module);
            }
        }

        return $text;
    }

    private function getModuleDocumentation(string $moduleName, TransformerModuleInterface|GeneratorModuleInterface $module): string
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

        $documentation = $module->getDocumentation();

        return "### $moduleName\n".$docToText($documentation);
    }
}
