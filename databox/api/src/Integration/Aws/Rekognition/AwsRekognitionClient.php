<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Integration\IntegrationConfig;
use Aws\Rekognition\RekognitionClient;

final readonly class AwsRekognitionClient
{
    private function createClient(IntegrationConfig $options): RekognitionClient
    {
        return new RekognitionClient([
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret' => $options['accessKeySecret'],
            ],
            'version' => 'latest',
        ]);
    }

    public function getImageLabels(string $path, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectLabels([
            'Image' => [
                'Bytes' => file_get_contents($path),
            ],
        ]);

        return $res->toArray();
    }

    public function getImageTexts(string $path, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectText([
            'Image' => [
                'Bytes' => file_get_contents($path),
            ],
        ]);

        return $res->toArray();
    }

    public function getImageFaces(string $path, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectFaces([
            'Attributes' => ['ALL'],
            'Image' => [
                'Bytes' => file_get_contents($path),
            ],
        ]);

        return $res->toArray();
    }
}
