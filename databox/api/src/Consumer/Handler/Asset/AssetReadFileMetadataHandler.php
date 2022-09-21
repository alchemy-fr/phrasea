<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;


class AssetReadFileMetadataHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_read_file_metadata';
    private FileStorageManager $storageManager;


    public function __construct(FileStorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function handle(EventMessage $message): void
    {
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
        $payload = $message->getPayload();
        $id = $payload['id'];
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset id = '%s'\n", __FILE__, __LINE__, $id), FILE_APPEND);

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) ASSET NOT FOUND \n", __FILE__, __LINE__), FILE_APPEND);
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset->getId() = '%s'\n", __FILE__, __LINE__, $asset->getId()), FILE_APPEND);
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset->getFile()->getPath() = '%s'\n", __FILE__, __LINE__, $asset->getFile()->getPath()), FILE_APPEND);
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset->getFile()->getFilename() = '%s'\n", __FILE__, __LINE__, $asset->getFile()->getFilename()), FILE_APPEND);

        if(($tmp = tmpfile()) !== false) {
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
            $tmpFilename = stream_get_meta_data($tmp)['uri'];
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) tmpFilename = '%s'\n", __FILE__, __LINE__, $tmpFilename), FILE_APPEND);
            $src = $this->storageManager->getStream($asset->getFile()->getPath());
            stream_copy_to_stream($src, $tmp);

            $mm = new MetadataManipulator();
            $meta = $mm->getAllMetadata(new \SplFileObject($tmpFilename));
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) meta: %s \n", __FILE__, __LINE__, var_export($meta, true)), FILE_APPEND);
            fclose($tmp);
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $assetId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $assetId,
        ]);
    }
}
