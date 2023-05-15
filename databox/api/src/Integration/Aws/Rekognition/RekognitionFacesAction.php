<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Entity\Core\Asset;

final class RekognitionFacesAction extends AbstractRekognitionAction
{
    protected function getCategory(): string
    {
        return AwsRekognitionIntegration::FACES;
    }

    protected function handleResult(Asset $asset, array $result, array $config): void
    {
    }
}
