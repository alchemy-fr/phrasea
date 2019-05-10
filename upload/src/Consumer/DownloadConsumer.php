<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use GuzzleHttp\Client;
use Mimey\MimeTypes;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;

class DownloadConsumer implements ConsumerInterface
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
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(
        FileStorageManager $storageManager,
        Client $client,
        LoggerInterface $logger,
        AssetManager $assetManager
    ) {
        $this->client = $client;
        $this->storageManager = $storageManager;
        $this->logger = $logger;
        $this->assetManager = $assetManager;
    }

    public function execute(AMQPMessage $msg)
    {
        try {
            $this->doExecute(json_decode($msg->getBody(), true));
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());

            return self::MSG_REJECT;
        }

        return self::MSG_ACK;
    }

    private function doExecute(array $msg): void
    {
        $url = $msg['url'];
        $id = $msg['id'];
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
        if (null === $extension) {
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
    }
}
