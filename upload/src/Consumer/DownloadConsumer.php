<?php

declare(strict_types=1);

namespace App\Consumer;

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

    public function __construct(FileStorageManager $storageManager, Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->storageManager = $storageManager;
        $this->logger = $logger;
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
        $response = $this->client->request('GET', $msg['url']);
        $contentType = $response->getHeaders()['Content-Type'][0] ?? 'text/plain';

        $mimes = new MimeTypes();
        $extension = $mimes->getExtension($contentType);
        $path = $this->storageManager->generatePath($extension);

        $this->storageManager->store($path, $response->getBody()->getContents());
    }
}
