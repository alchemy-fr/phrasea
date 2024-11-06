<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle;

use Alchemy\ConfiguratorBundle\Dumper\JsonDumper;
use Alchemy\ConfiguratorBundle\Pusher\BucketPusher;

final readonly class Deployer
{
    public function __construct(
        private JsonDumper $dumper,
        private BucketPusher $pusher,
    )
    {
    }

    public function deploy(): void
    {
        $data = $this->dumper->dump();
        $this->pusher->pushToBucket('config.json', $data);
    }
}
