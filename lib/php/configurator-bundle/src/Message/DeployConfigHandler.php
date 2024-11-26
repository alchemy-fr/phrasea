<?php

namespace Alchemy\ConfiguratorBundle\Message;

use Alchemy\ConfiguratorBundle\Deployer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeployConfigHandler
{
    public function __construct(
        private Deployer $deployer,
    )
    {
    }

    public function __invoke(DeployConfig $message): void
    {
        $this->deployer->deploy();
    }
}
