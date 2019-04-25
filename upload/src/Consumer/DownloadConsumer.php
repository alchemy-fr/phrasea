<?php

declare(strict_types=1);

namespace App\Consumer;

use League\Flysystem\FilesystemInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use Throwable;

class DownloadConsumer implements ConsumerInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function execute(AMQPMessage $msg)
    {
        try {
            $this->doExecute(json_decode($msg->getBody(), true));
        } catch (Throwable $e) {
            var_dump($e->getMessage());
            return self::MSG_REJECT;
        }

        return self::MSG_ACK;
    }

    private function doExecute(array $msg): void
    {
        $url = $msg['url'];

        $uuid = Uuid::uuid4()->toString();
        $path = sprintf(
            '%s/%s/%s.%s',
            substr($uuid, 0, 2),
            substr($uuid, 2, 2),
            $uuid,
            'bin' // TODO guess extension from request
        );

        $stream = fopen($url, 'r');
        $this->filesystem->writeStream($path, $stream);
        fclose($stream);
    }
}
