<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Integration\IntegrationConfig;
use Aws\Rekognition\RekognitionClient;

final readonly class AwsRekognitionClient
{
    /**
     * Hard limit of the Rekognition API for Image.Bytes.
     */
    private const int MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private function readImage(string $path): string
    {
        $size = filesize($path);
        if (false === $size || $size > self::MAX_IMAGE_BYTES) {
            throw new \InvalidArgumentException(sprintf('Image is %s bytes: AWS Rekognition accepts at most %d bytes. Configure the integration to send a smaller rendition.', false === $size ? 'unknown' : (string) $size, self::MAX_IMAGE_BYTES));
        }

        return file_get_contents($path);
    }

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
                'Bytes' => $this->readImage($path),
            ],
        ]);

        return $res->toArray();
    }

    public function getImageTexts(string $path, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $res = $client->detectText([
            'Image' => [
                'Bytes' => $this->readImage($path),
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
                'Bytes' => $this->readImage($path),
            ],
        ]);

        return $res->toArray();
    }
}
