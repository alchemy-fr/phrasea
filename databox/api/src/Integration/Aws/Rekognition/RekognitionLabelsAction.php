<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Entity\Core\Asset;

final class RekognitionLabelsAction extends AbstractRekognitionAction
{
    protected function getCategory(): string
    {
        return AwsRekognitionIntegration::LABELS;
    }

    protected function handleResult(Asset $asset, array $result, array $config): void
    {
        if (!empty($result['labels']) && !empty($config['labels']['attributes'] ?? [])) {
            $this->saveTextsToAttributes($asset, array_map(fn (array $text): array => [
                'value' => $text['Name'],
                'confidence' => $text['Confidence'],
            ], $result['labels']['Labels']), $config['labels']['attributes']);
        }
    }
}
