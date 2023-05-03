<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use App\Asset\FileUrlResolver;
use App\Entity\Core\File;
use Clarifai\API\ClarifaiClient as ClarifaiClientSDK;
use Clarifai\DTOs\Inputs\ClarifaiURLImage;
use Clarifai\DTOs\Outputs\ClarifaiOutput;
use Clarifai\DTOs\Predictions\Concept;

class ClarifaiClient
{
    public function __construct(private readonly FileUrlResolver $fileUrlResolver)
    {
    }

    public function getImageConcepts(File $file, string $apiKey): array
    {
        $client = new ClarifaiClientSDK($apiKey);

        $url = $this->fileUrlResolver->resolveUrl($file);

        $model = $client->publicModels()->generalModel();
        $response = $model->batchPredict([
//            new ClarifaiURLImage($url),
            new ClarifaiURLImage('https://www.planetesauvage.com/fileadmin/_processed_/d/c/csm_elephant-bandeau_d2b55e50ca.jpg'),
        ])->executeSync();

        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Response error: [%d] %s - %s', $response->status()->statusCode(), $response->status()->description(), $response->status()->errorDetails()));
        }

        /** @var ClarifaiOutput[] $outputs */
        $outputs = $response->get();

        $concepts = [];
        foreach ($outputs as $output) {
            /** @var Concept $concept */
            foreach ($output->data() as $concept) {
                $concepts[$concept->name()] = $concept->value();
            }
        }

        return $concepts;
    }
}
