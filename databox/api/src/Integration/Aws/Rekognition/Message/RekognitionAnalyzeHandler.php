<?php

namespace App\Integration\Aws\Rekognition\Message;

use App\Integration\Aws\Rekognition\AwsRekognitionIntegration;
use App\Integration\Aws\Rekognition\RekognitionAnalyzer;
use App\Integration\Message\AbstractFileActionMessageHandler;
use App\Integration\PusherTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RekognitionAnalyzeHandler extends AbstractFileActionMessageHandler
{
    use PusherTrait;

    public function __construct(
        private readonly RekognitionAnalyzer $rekognitionAnalyzer,
    ) {
    }

    public function __invoke(RekognitionAnalyze $message): void
    {
        $file = $this->getFile($message);
        $config = $this->getConfig($message);

        $this->rekognitionAnalyzer->analyze(null, $file, $message->getCategory(), $config);

        $this->triggerFilePush(AwsRekognitionIntegration::getName(), $file, [
            'cat' => $message->getCategory(),
        ], direct: true);
    }
}
