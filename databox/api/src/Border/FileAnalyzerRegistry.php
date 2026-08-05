<?php

namespace App\Border;

use App\Border\FileAnalyzer\AnalyzerInterface;
use App\Border\FileAnalyzer\FileAnalyzerConfigHelper;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final readonly class FileAnalyzerRegistry
{
    public function __construct(
        #[AutowireLocator(services: AnalyzerInterface::TAG, defaultIndexMethod: 'getName')]
        private ContainerInterface $analyzers,
    ) {
    }

    public function getAnalyzer(string $name): AnalyzerInterface
    {
        if (!$this->analyzers->has($name)) {
            throw new \InvalidArgumentException(sprintf('Analyzer "%s" not found.', $name));
        }

        /* @var AnalyzerInterface $analyzer */
        return $this->analyzers->get($name);
    }

    public function processConfiguration(AnalyzerInterface $analyzer, array $config): array
    {
        $treeBuilder = FileAnalyzerConfigHelper::createBaseTree($analyzer::getName());
        $children = $treeBuilder->getRootNode()->children();

        $analyzer->buildConfiguration($children);

        $node = $treeBuilder->buildTree();

        $processor = new Processor();

        return $processor->process($node, ['root' => $config]);
    }
}
