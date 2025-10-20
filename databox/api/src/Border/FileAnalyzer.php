<?php

namespace App\Border;

use App\Border\Analyzer\AnalyzerInterface;
use App\Entity\Core\File;
use App\Service\Asset\FileFetcher;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Yaml\Yaml;

final readonly class FileAnalyzer
{
    public function __construct(
        #[AutowireLocator(services: AnalyzerInterface::TAG, defaultIndexMethod: 'getName')]
        private ContainerInterface $analyzers,
        private FileFetcher $fileFetcher,
    ) {
    }

    public function analyzeFile(File $file, bool $force = false): void
    {
        if ($file->isAnalyzed() && !$force) {
            return;
        }

        if (!$file->isPathPublic()) {
            $file->setAnalysis([
                'status' => File::ANALYSIS_SKIPPED,
                'message' => 'File analysis skipped for non accessible files.',
            ]);

            return;
        }

        $filePath = $this->fileFetcher->getFile($file);
        try {
            $outputs = [];
            foreach ($this->getAnalyzers($file) as $analyzerConfig) {
                $analyzer = $this->getAnalyzer($analyzerConfig);
                $analyzerConfig = $this->processConfiguration(
                    $analyzer,
                    $analyzerConfig,
                );

                $output = $analyzer->analyzeFile($file, $filePath, $analyzerConfig);
                $outputs[] = [
                    'name' => $analyzerConfig['name'],
                    'output' => $output->toArray(),
                ];
                if (!$output->isSuccessful()) {
                    $file->setAnalysis([
                        'status' => File::ANALYSIS_FAILED,
                        'results' => $outputs,
                    ]);

                    return;
                }
            }

            $file->setAnalysis([
                'status' => File::ANALYSIS_SUCCESS,
                'results' => $outputs,
            ]);
        } finally {
            @unlink($filePath);
        }
    }

    /**
     * @return bool Whether to proceed File analysis
     */
    public function preAnalyzeFile(File $file, bool $force = false): bool
    {
        if ($file->isAnalyzed() && !$force) {
            return false;
        }

        $outputs = [];
        $fileContentsRequired = false;

        foreach ($this->getAnalyzers($file) as $analyzerConfig) {
            $analyzer = $this->getAnalyzer($analyzerConfig);

            if ($analyzer->requiresFileContent($file, $analyzerConfig)) {
                $fileContentsRequired = true;

                continue;
            }

            $output = $analyzer->analyzeFile($file, null, $analyzerConfig);
            $outputs[] = [
                'name' => $analyzerConfig['name'],
                'output' => $output->toArray(),
            ];
            if (!$output->isSuccessful()) {
                $file->setAnalysis($outputs);

                return false;
            }
        }

        return $fileContentsRequired;
    }

    private function getAnalyzer(array $config): AnalyzerInterface
    {
        if (!isset($config['name'])) {
            throw new \InvalidArgumentException('Analyzer configuration error: "name" is not set.');
        }

        if (!$this->analyzers->has($config['name'])) {
            throw new \InvalidArgumentException(sprintf('Analyzer "%s" not found.', $config['name']));
        }

        /* @var AnalyzerInterface $analyzer */
        return $this->analyzers->get($config['name']);
    }

    private function getAnalyzers(File $file): array
    {
        $fileAnalyzers = $file->getWorkspace()->getFileAnalyzers();
        $data = Yaml::parse($fileAnalyzers);

        return $data['analyzers'] ?? [];
    }

    private function processConfiguration(AnalyzerInterface $analyzer, array $config): array
    {
        $treeBuilder = new TreeBuilder('root');
        $children = $treeBuilder->getRootNode()->children();
        $children
            ->scalarNode('name')
                ->cannotBeEmpty()
                ->isRequired()
            ->end();
        $analyzer->buildConfiguration($children);

        $node = $treeBuilder->buildTree();

        $processor = new Processor();

        return $processor->process($node, ['root' => $config]);
    }

    public function validateAnalyzersConfiguration(array $analyzers): void
    {
        foreach ($analyzers['analyzers'] ?? [] as $config) {
            $analyzer = $this->getAnalyzer($config);

            $config = $this->processConfiguration(
                $analyzer,
                $config
            );

            $analyzer->validateConfiguration($config);
        }
    }
}
