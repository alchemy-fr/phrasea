<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Transformer\Documentation;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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

        $this->addArgument('config', InputArgument::OPTIONAL, 'A build config YAML file to validate')
            ->setHelp('Display rendition modules documentation, or validate a config file.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null !== ($configPath = $input->getArgument('config'))) {
            $config = $this->yamlLoader->load($configPath);

            foreach (FamilyEnum::cases() as $family) {
                $familyConfig = $config->getFamily($family);
                if (null === $familyConfig) {
                    continue;
                }
                foreach ($familyConfig->getTransformations() as $transformation) {
                    $transformerName = $transformation->getModule();

                    /** @var TransformerModuleInterface $transformer */
                    $transformer = $this->transformers->get($transformerName);

                    try {
                        $this->checkTransformerConfiguration($transformerName, $transformer, $transformation->asArray());
                    } catch (\Throwable $e) {
                        $msg = sprintf("Error in module \"%s\"\n%s", $transformerName, $e->getMessage());
                        throw new InvalidConfigurationException($msg);
                    }
                }
            }
            $output->writeln('Configuration is valid.');
        } else {
            foreach ($this->transformers->getProvidedServices() as $transformerName => $transformerFqcn) {

                /** @var TransformerModuleInterface $transformer */
                $transformer = $this->transformers->get($transformerName);
                $output->writeln($this->getTransformerDocumentation($transformerName, $transformer));
            }
        }

        return 0;
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

    private function checkTransformerConfiguration(string $transformerName, TransformerModuleInterface $transformer, array $options): void
    {
        $documentation = $transformer->getDocumentation();
        $treeBuilder = $documentation->getTreeBuilder();

        $processor = new Processor();
        $processor->process($treeBuilder->buildTree(), ['root' => $options]);
    }
}
