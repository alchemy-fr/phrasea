<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'alchemy:rendition-factory:conf:validate', help: 'Validate a config file.')]
class ConfigurationValidateCommand extends Command
{
    public function __construct(
        private readonly YamlLoader $yamlLoader,
        private readonly BuildConfigValidator $validator,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('A build config YAML file to validate', name: 'config')]
        string $configFile,
    ): int {
        $config = $this->yamlLoader->load($configFile);
        $this->validator->validate($config);

        $output->writeln('Configuration is valid.');

        return Command::SUCCESS;
    }
}
