<?php

namespace App\Integration\RemoveBg\Message;

use App\Integration\Message\AbstractFileActionMessageHandler;
use App\Integration\PusherTrait;
use App\Integration\RemoveBg\RemoveBgIntegration;
use App\Integration\RemoveBg\RemoveBgProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveBgCallHandler extends AbstractFileActionMessageHandler
{
    use PusherTrait;

    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
    ) {
    }

    public function __invoke(RemoveBgCall $message): void
    {
        $file = $this->getFile($message);
        $config = $this->getConfig($message);

        $this->removeBgProcessor->process($file, $config);

        $this->triggerFilePush(RemoveBgIntegration::getName(), $file, [], direct: true);
    }
}
