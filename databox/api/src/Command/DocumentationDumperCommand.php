<?php

declare(strict_types=1);

namespace App\Command;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //        $output->writeln('# '.$this->renditionBuilderConfigurationDocumentation::getName());
        //        $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());

        $this->dumpInitialValuesDocumentation($output);

        return Command::SUCCESS;
    }

    private function dumpInitialValuesDocumentation(OutputInterface $output)
    {
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
                $output->write($definition['isMultiple'] ?? false ? sprintf(' ; [x] `multi`') : '');

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

                $nCols = 0;
                foreach ($test['metadata'] as $values) {
                    $nCols = max($nCols, is_array($values) ? count($values) : 1);
                }
                foreach ($test['metadata'] as $metadataName => $values) {
                    $v = is_array($values) ? $values : [$values];
                    $output->writeln(sprintf('| %s | %s |', $metadataName, join(' | ', $v)));
                }

                $output->writeln('### ... initial attribute(s) value(s)');
                foreach ($test['expected'] as $attributeName => $value) {
                    //  $output->writeln(sprintf('- __%s__:', $attributeName));
                    $this->dumpExpected($output, $attributeName, $value, 1);
                }
            }

        }
    }

    private function codeBlockIndented(OutputInterface $output, string $code, string $language, int $indent = 1): void
    {
        $tab = str_repeat('  ', $indent);
        $output->writeln(sprintf('%s```%s', $tab, $language));
        foreach (explode("\n", $code) as $line) {
            $output->writeln(sprintf('%s%s', $tab, $line));
        }
        $output->writeln(sprintf('%s```', $tab));
    }

    private function dumpExpected(OutputInterface $output, string $attributeName, $value, int $indent = 0): void
    {
        $tab = str_repeat('  ', $indent);
        if (is_array($value)) {
            if ($this->isNumericArray($value)) {
                // a simple list of values
                $n = 1;
                foreach ($value as $v) {
                    $output->writeln(sprintf('%s%s #%d: `%s`', $tab, $attributeName, $n++, $v));
                    $output->writeln('');
                }
            } else {
                // an array with key=locale
                foreach ($value as $locale => $v) {
                    $output->writeln(sprintf('%s- locale `%s`:', $tab, $locale));
                    $output->writeln('');
                    $this->dumpExpected($output, $attributeName, $v, $indent + 1);
                }
            }
        } else {
            // a single value
            $output->writeln(sprintf('%s`%s: %s`', $tab, $attributeName, $value));
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
