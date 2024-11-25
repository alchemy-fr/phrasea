<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Transformer\DocumentationTree;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[AsCommand('alchemy:rendition-factory:config')]
class ConfigCommand extends Command
{
    public function __construct(
        private readonly YamlLoader $yamlLoader,
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceLocator $transformers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('build-config', InputArgument::OPTIONAL, 'A build config YAML file to validate')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Display optiond for a specific module')
            ->setHelp('Display the options for a module, or validate a build-config YAML file.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($transformerName = $input->getOption('module')) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($transformerName);
            $output->writeln($this->getTransformerDocumentation($transformerName, $transformer));
        }

        if ($buildConfigPath = $input->getArgument('build-config')) {
            $buildConfig = $this->yamlLoader->load($buildConfigPath);

            foreach (FamilyEnum::cases() as $family) {
                $familyConfig = $buildConfig->getFamily($family);
                if (null === $familyConfig) {
                    continue;
                }

                $output->writeln(sprintf('# Family `%s`:', $family->name));
                foreach ($familyConfig->getTransformations() as $transformation) {
                    $transformerName = $transformation->getModule();
                   // $output->writeln(sprintf('  - %s', $transformerName));

                    /** @var TransformerModuleInterface $transformer */
                    $transformer = $this->transformers->get($transformerName);
                    $output->writeln($this->getTransformerDocumentation($transformerName, $transformer));

                    $this->checkTransformerConfiguration($transformerName, $transformer, $transformation->getOptions());
                }
            }
        }

        return 0;
    }

    private function getTransformerDocumentation(string $transformerName, TransformerModuleInterface $transformer): string
    {
        $docToText = function(DocumentationTree $documentation, int $depth=0) use (&$docToText): string {

            $text = '';
            if($t = $documentation->getHeader()) {
                $text .= $t . "\n";
            }

            $treeBuilder = $documentation->getTreeBuilder();
            $node = $treeBuilder->buildTree();
            $dumper = new YamlReferenceDumper();

            $t = $dumper->dumpNode($node);
            $t = preg_replace("#^root:(\n( {4})?|\s+\[])#", "-\n", (string) $t);
            $t = str_replace("\n\n", "\n", $t);
            $t = str_replace("\n", "\n    ", $t);

            $text .= "```yaml\n" . $t . "\n```\n";

            foreach ($documentation->getChildren() as $child) {
                $text .= $docToText($child, $depth+1);
            }

            if($t = $documentation->getFooter()) {
                $text .= $t . "\n";
            }

            return $text;
        };


        $doc = "\n\n## $transformerName transformer module\n";
        if (method_exists($transformer, 'getDocumentation')) {
            $documentation = $transformer->getDocumentation();
            $doc .= $docToText($documentation);
        }

        return $doc;
    }

    private function checkTransformerConfiguration(string $transformerName, TransformerModuleInterface $transformer, array $options): void
    {
        if (method_exists($transformer, 'getDocumentation')) {
            $documentation = $transformer->getDocumentation();
            $treeBuilder = $documentation->getTreeBuilder();

            $processor = new Processor();
            $processor->process($treeBuilder->buildTree(), ['root' => $options]);
        }
    }

    private function no_getTransformerDocumentation(string $fqcn, string $transformerName, TransformerModuleInterface $transformer): string
    {
        $doc = "\n\n## $transformerName\n";

        $reflectionClass = new \ReflectionClass($fqcn);
        if ($reflectionClass->hasMethod('getDocumentationHeader') && $reflectionClass->getMethod('getDocumentationHeader')->class == $fqcn) {
            $doc .= $transformer->getDocumentationHeader()."\n";
        }

        if (method_exists($transformer, 'buildConfiguration')) {
            $treeBuilder = new TreeBuilder('root');
            $transformer->buildConfiguration($treeBuilder->getRootNode()->children());

            $node = $treeBuilder->buildTree();
            $dumper = new YamlReferenceDumper();

            $t = $dumper->dumpNode($node);
            $t = preg_replace("#^root:(\n( {4})?|\s+\[])#", "-\n", (string) $t);
            $t = str_replace("\n\n", "\n", $t);
            $t = str_replace("\n", "\n    ", $t);
            //            $t = preg_replace("#^root:(\n( {4})?|\s+\[])#", '', (string)$t);
            //            $t = preg_replace("#\n {4}#", "\n", $t);
            //            $t = preg_replace("#\n\n#", "\n", $t);
            //            $t = trim(preg_replace("#^\n+#", '', $t));

            $doc .= "```yaml\n".$t."```\n";
        }

        if ($reflectionClass->hasMethod('getDocumentationFooter') && $reflectionClass->getMethod('getDocumentationFooter')->class == $fqcn) {
            $doc .= $transformer->getDocumentationFooter()."\n";
        }

        return $doc;
    }
}
