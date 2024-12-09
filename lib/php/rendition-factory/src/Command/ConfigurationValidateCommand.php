<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:rendition-factory:conf:validate')]
class ConfigurationValidateCommand extends Command
{
    public function __construct(
        private readonly YamlLoader $yamlLoader,
        private readonly BuildConfigValidator $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('config', InputArgument::REQUIRED, 'A build config YAML file to validate')
            ->setHelp('Validate a config file.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->yamlLoader->load($input->getArgument('config'));
        $this->validator->validate($config);

        $output->writeln('Configuration is valid.');

        return Command::SUCCESS;
    }
}
