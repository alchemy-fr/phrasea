<?php

namespace App\Border;

use App\Border\FileAnalyzer\AnalyzerInterface;
use App\Border\FileAnalyzer\FileAnalyzerConfigHelper;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class FileAnalyzerRegistry
{
    public function __construct(
        #[AutowireLocator(services: AnalyzerInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $analyzers,
    ) {
    }

    /**
     * @return iterable<string, AnalyzerInterface>
     */
    public function getAnalyzers(): iterable
    {
        foreach ($this->analyzers->getProvidedServices() as $name => $fqcn) {
            yield $name => $this->getAnalyzer($name);
        }
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
