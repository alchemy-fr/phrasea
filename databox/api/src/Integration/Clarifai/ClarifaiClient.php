<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use App\Asset\FileUrlResolver;
use App\Entity\Core\File;
use Clarifai\Api\Data;
use Clarifai\Api\Image;
use Clarifai\Api\Input;
use Clarifai\Api\PostModelOutputsRequest;
use Clarifai\Api\Status\StatusCode;
use Clarifai\Api\V2Client;
use Clarifai\ClarifaiClient as Client;
use Exception;

// TODO remove abstract
abstract class ClarifaiClient
{
    private V2Client $client;
    private string $apiKey;
    private FileUrlResolver $fileUrlResolver;

    public function __construct(string $apiKey, FileUrlResolver $fileUrlResolver)
    {
        $this->client = Client::grpc();
        $this->apiKey = $apiKey;
        $this->fileUrlResolver = $fileUrlResolver;
    }

    public function getImageConcepts(File $file): array
    {
        $metadata = ['Authorization' => ['Key '.$this->apiKey]];
        [$response, $status] = $this->client->PostModelOutputs(
            new PostModelOutputsRequest([
                'model_id' => 'aaa03c23b3724a16a56b629203edc62c',  // This is the ID of the publicly available General model.
                'inputs' => [
                    new Input([
                        'data' => new Data([
                            'image' => new Image([
                                //'url' => $this->fileUrlResolver->resolveUrl($file), TODO
                                'url' => 'https://www.planetesauvage.com/fileadmin/_processed_/d/c/csm_elephant-bandeau_d2b55e50ca.jpg',
                            ]),
                        ]),
                    ]),
                ]
            ]),
            $metadata
        )->wait();

        if ($status->code !== 0) {
            throw new Exception("Error: {$status->details}");
        }
        if ($response->getStatus()->getCode() != StatusCode::SUCCESS) {
            throw new Exception("Failure response: " . $response->getStatus()->getDescription() . " " .
                $response->getStatus()->getDetails());
        }

        $concepts = [];
        foreach ($response->getOutputs()[0]->getData()->getConcepts() as $concept) {
            $concepts[$concept->getName()] = $concept->getValue();
        }

        return $concepts;
    }
}
