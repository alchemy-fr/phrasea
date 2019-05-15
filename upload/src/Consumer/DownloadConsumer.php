<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use GuzzleHttp\Client;
use Mimey\MimeTypes;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;

class DownloadConsumer extends AbstractConsumer
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var FileStorageManager
     */
    private $storageManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(
        FileStorageManager $storageManager,
        Client $client,
        AssetManager $assetManager
    ) {
        $this->client = $client;
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
    }

    protected function doExecute(array $message): int
    {
        $url = $message['url'];
        $id = $message['id'];
        $response = $this->client->request('GET', $url);
        $headers = $response->getHeaders();
        $contentType = $headers['Content-Type'][0] ?? 'application/octet-stream';

        $originalName = basename(explode('?', $url, 2)[0]);
        if (isset($headers['Content-Disposition'][0])) {
            $contentDisposition = $headers['Content-Disposition'][0];
            if (preg_match('#\s+filename="(.+?)"#', $contentDisposition, $regs)) {
                $originalName = $regs[1];
            }
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = !empty($extension) ? $extension : null;
        if (!$extension && 'application/octet-stream' !== $contentType) {
            $mimes = new MimeTypes();
            $extension = $mimes->getExtension($contentType);
            $originalName .= '.'.$extension;
        }

        $path = $this->storageManager->generatePath($extension);

        $this->storageManager->store($path, $response->getBody()->getContents());

        $this->assetManager->createAsset(
            $path,
            $contentType,
            $originalName,
            $response->getBody()->getSize(),
            $id
        );

        return ConsumerInterface::MSG_ACK;
    }
}
