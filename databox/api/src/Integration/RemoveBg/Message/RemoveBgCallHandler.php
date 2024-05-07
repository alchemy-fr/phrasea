<?php

namespace App\Integration\RemoveBg\Message;

use App\Integration\IntegrationManager;
use App\Integration\Message\AbstractFileActionMessageHandler;
use App\Integration\RemoveBg\RemoveBgProcessor;

final class RemoveBgCallHandler extends AbstractFileActionMessageHandler
{
    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
    )
    {
    }

    public function __invoke(RemoveBgCall $message): void
    {
        $file = $this->getFile($message);
        $config = $this->getConfig($message);

        $this->removeBgProcessor->process($file, $config);
    }
}
