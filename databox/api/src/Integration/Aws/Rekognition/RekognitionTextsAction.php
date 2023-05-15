<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Entity\Core\Asset;

final class RekognitionTextsAction extends AbstractRekognitionAction
{
    protected function getCategory(): string
    {
        return AwsRekognitionIntegration::TEXTS;
    }

    protected function handleResult(Asset $asset, array $result, array $config): void
    {
        if (!empty($result['texts']) && !empty($config['texts']['attributes'] ?? [])) {
            $this->saveTextsToAttributes($asset, array_map(fn (array $text): array => [
                'value' => $text['DetectedText'],
                'confidence' => $text['Confidence'],
            ], array_filter($result['texts']['TextDetections'], fn (array $text): bool => 'LINE' === $text['Type'])), $config['texts']['attributes']);
        }
    }
}
