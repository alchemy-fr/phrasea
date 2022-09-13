<?php

declare(strict_types=1);

namespace App\Integration\AwsRekognition;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use Aws\Rekognition\RekognitionClient;

class AwsRekognitionClient
{
    private FileFetcher $fileFetcher;

    public function __construct(FileFetcher $fileFetcher)
    {
        $this->fileFetcher = $fileFetcher;
    }

    private function createClient(array $options): RekognitionClient
    {
        return new RekognitionClient([
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret'  => $options['accessKeySecret'],
            ],
            'version' => 'latest',
        ]);
    }

    public function getImageLabels(File $file, array $options): array
    {
        $client = $this->createClient($options);

        $path = $this->fileFetcher->getFile($file);

        $res = $client->detectLabels([
            'Image' => [
                'Bytes' => file_get_contents($path),
            ]
        ]);

        dump($res);

        return $res->get('Labels');
    }
}
