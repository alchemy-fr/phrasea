<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle\Command;

use Alchemy\ConfiguratorBundle\Deployer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'configurator:push-to-bucket', description: 'Push configuration to S3 bucket')]
final class PushConfigToBucketCommand extends Command
{
    public function __construct(
        private readonly Deployer $deployer,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->deployer->deploy();
        $output->writeln('Configuration pushed to bucket');

        return Command::SUCCESS;
    }
}
