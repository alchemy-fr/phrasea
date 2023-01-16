<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Aws\Rekognition\RekognitionClient;

class AwsRekognitionClient
{
    private function createClient(array $options): RekognitionClient
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

    public function getImageLabels(string $path, array $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectLabels([
            'Image' => [
                'Bytes' => file_get_contents($path),
            ],
        ]);

        return $res->toArray();
    }

    public function getImageTexts(string $path, array $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectText([
            'Image' => [
                'Bytes' => file_get_contents($path),
            ],
        ]);

        return $res->toArray();
    }

    public function getImageFaces(string $path, array $options): array
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
