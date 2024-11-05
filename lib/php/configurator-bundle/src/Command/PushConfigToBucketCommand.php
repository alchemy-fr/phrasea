<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle\Command;

use Alchemy\ConfiguratorBundle\Dumper\JsonDumper;
use Alchemy\ConfiguratorBundle\Pusher\BucketPusher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'configurator:push-to-bucket', description: 'Push configuration to S3 bucket')]
final class PushConfigToBucketCommand extends Command
{
    public function __construct(
        private readonly JsonDumper $dumper,
        private readonly BucketPusher $pusher,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->dumper->dump();
        $this->pusher->pushToBucket('config.json', $data);

        $output->writeln(sprintf('<comment>Configuration pushed to bucket</comment>:
%s', $data));

        return Command::SUCCESS;
    }
}
