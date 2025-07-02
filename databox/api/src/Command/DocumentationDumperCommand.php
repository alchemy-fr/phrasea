<?php

declare(strict_types=1);

namespace App\Command;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        private readonly RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Dump code-generated documentation(s)')
            ->addArgument('part', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Part(s) to dump. If not specified, all parts will be dumped.')
            ->setHelp('parts: "rendition", "initial-attribute"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($input->getArgument('part'))) {
            $input->setArgument('part', ['rendition', 'initial-attribute']);
        }

        foreach ($input->getArgument('part') as $part) {
            if (!in_array($part, ['rendition', 'initial-attribute'])) {
                $output->writeln(sprintf('<error>Unknown part "%s". Valid parts are "rendition" and "initial-attribute".</error>', $part));

                return Command::FAILURE;
            }
            switch ($part) {
                case 'rendition':
                    $output->writeln('# '.$this->renditionBuilderConfigurationDocumentation::getName());
                    $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());
                    break;
                case 'initial-attribute':
                    $this->dumpInitialValuesDocumentation($output);
                    break;
            }
        }

        return Command::SUCCESS;
    }

    private function dumpInitialValuesDocumentation(OutputInterface $output)
    {
        $output->writeln('# Initial Attribute Values');
        $n = 0;
        foreach (Yaml::parseFile(__DIR__.'/../../tests/fixtures/metadata/InitialAttributeValuesResolverData.yaml') as $test) {
            if (!($test['about'] ?? false)) {
                continue;
            }

            if ($n++ > 0) {
                $output->writeln('');
                $output->writeln('---');
                $output->writeln('');
            }

            $output->writeln(sprintf('## %s', $test['about']['title'] ?? ''));
            if ($description = $test['about']['description'] ?? '') {
                $output->writeln(sprintf('%s', $description));
            }

            $output->writeln('### Attribute(s) definition(s) ...');
            foreach ($test['definitions'] as $name => $definition) {
                $output->write(sprintf('- __%s__:', $name));
                $output->write(sprintf('   `%s`', $definition['fieldType'] ?? 'text'));
                $output->write(sprintf(' ; [%s] `multi`', $definition['isMultiple'] ?? false ? 'X' : ' '));
                $output->write(sprintf(' ; [%s] `translatable`', $definition['isTranslatable'] ?? false ? 'X' : ' '));

                $output->writeln('');

                if (is_array($definition['initialValues'] ?? null)) {
                    foreach ($definition['initialValues'] as $locale => $initializer) {
                        $output->writeln(sprintf('  - locale `%s`', $locale));
                        $code = json_encode(json_decode($initializer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $this->codeBlockIndented($output, $code, 'json', 1);
                    }
                } elseif (null !== ($definition['initialValues'] ?? null)) {
                    $initializer = $definition['initialValues'];
                    $code = json_encode(json_decode($initializer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $this->codeBlockIndented($output, $code, 'json', 0);
                }

                $output->writeln('');
                $output->writeln('### ... with file metadata ...');
                $output->writeln('| metadata | value(s) |');
                $output->writeln('|---|---|');
                foreach ($test['metadata'] as $metadataName => $values) {
                    $v = is_array($values) ? $values : [$values];
                    $output->writeln(sprintf('| %s | `%s` |', $metadataName, join('` ; `', $v)));
                }

                $output->writeln('');
                $output->writeln('### ... set attribute(s) initial value(s)');
                $this->dumpExpected($output, $test['expected'], 1);

                $output->writeln('');
            }

        }
    }

    private function codeBlockIndented(OutputInterface $output, string $code, string $language, int $indent = 0): void
    {
        $tab = str_repeat('  ', $indent);
        $output->writeln(sprintf('%s```%s', $tab, $language));
        foreach (explode("\n", $code) as $line) {
            $output->writeln(sprintf('%s%s', $tab, $line));
        }
        $output->writeln(sprintf('%s```', $tab));
    }

    private function dumpExpected(OutputInterface $output, array $expected, int $indent = 0): void
    {
        $tab = str_repeat('  ', $indent);
        $output->writeln(sprintf('%s| Attributes | initial value(s) |', $tab));
        $output->writeln(sprintf('%s|---|---|', $tab));
        foreach ($expected as $attributeName => $value) {
            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    // a simple array of values
                    $n = 1;
                    foreach ($value as $v) {
                        $output->writeln(sprintf('%s| %s #%d | `%s` |', $tab, $attributeName, $n++, $v));
                        // $output->writeln('');
                    }
                } else {
                    // an array with key=locale
                    foreach ($value as $locale => $v) {
                        $output->writeln(sprintf('%s| __locale `%s`__ |   |', $tab, $locale));
                        if (is_array($v)) {
                            // an array of values
                            foreach ($v as $n => $w) {
                                $output->writeln(sprintf('%s| %s #%d | `%s` |', $tab, $attributeName, $n + 1, $w));
                            }
                        } else {
                            $output->writeln(sprintf('%s| %s  | `%s` |', $tab, $attributeName, $v));
                        }
                        // $output->writeln('');
                    }
                }
            } else {
                // a single value
                $output->writeln(sprintf('%s| %s | `%s`', $tab, $attributeName, $value));
            }
        }
    }

    private function isNumericArray($a): bool
    {
        if (!is_array($a)) {
            return false;
        }
        foreach ($a as $k => $v) {
            if (!is_numeric($k)) {
                return false;
            }
        }

        return true;
    }
}
