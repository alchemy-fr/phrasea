<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

final class RekognitionTextsAction extends AbstractRekognitionAction
{
    protected function getCategory(): string
    {
        return AwsRekognitionIntegration::TEXTS;
    }
}
