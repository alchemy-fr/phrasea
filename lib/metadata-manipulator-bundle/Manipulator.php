<?php

namespace Alchemy\MetadataManipulatorBundle;

use Psr\Log\LoggerInterface;

class Manipulator
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}