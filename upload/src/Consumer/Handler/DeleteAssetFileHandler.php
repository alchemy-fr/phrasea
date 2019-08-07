<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\BulkData;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use League\Flysystem\FilesystemInterface;
use Throwable;

class DeleteAssetFileHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'delete_asset_file';
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function handle(EventMessage $message): void
    {
        $path = $message->getPayload()['path'];

        $this->filesystem->delete($path);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'fast_events';
    }
}
