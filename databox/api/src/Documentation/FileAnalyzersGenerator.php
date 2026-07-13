<?php

declare(strict_types=1);

namespace App\Documentation;

use Alchemy\CoreBundle\Documentation\DocumentationGenerator;
use Alchemy\RenditionFactory\Transformer\Documentation;
use App\Border\Analyzer\AnalyzerInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class FileAnalyzersGenerator extends DocumentationGenerator
{
    public function __construct(
        #[AutowireLocator(AnalyzerInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $analyzers,
    ) {
    }

    public function getPath(): string
    {
        return '_file_analyzers.md';
    }

    public function getContent(): string
    {
        $text = '';
        foreach ($this->analyzers->getProvidedServices() as $name => $analyzerFCQN) {
            /** @var AnalyzerInterface $analyzer */
            $analyzer = $this->analyzers->get($name);
            $text .= $this->getAnalyzerDocumentation($name, $analyzer);
        }

        return $text;
    }

    private function getAnalyzerDocumentation(string $analyzerName, AnalyzerInterface $analyzer): string
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
            $t = preg_replace("#^root:($|(\s+)\[]$)#m", "-\n", $t);
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

        $documentation = $analyzer->getDocumentation();

        return "### $analyzerName\n".$docToText($documentation);
    }
}
