<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Exception\CreateTemporaryFileException;
use App\Exception\StreamCopyException;
use App\Metadata\MetadataNormalizer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;


class ReadMetadataHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'read_file_metadata';
    private FileStorageManager $storageManager;
    private MetadataNormalizer $metadataNormalizer;

    public function __construct(FileStorageManager $storageManager, MetadataNormalizer $metadataNormalizer)
    {
        $this->storageManager = $storageManager;
        $this->metadataNormalizer = $metadataNormalizer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        if( ($tmp = tmpfile()) !== false) {
            $tmpFilename = stream_get_meta_data($tmp)['uri'];
            $src = $this->storageManager->getStream($file->getPath());
            if(stream_copy_to_stream($src, $tmp) !== false) {

                $mm = new MetadataManipulator();
                $meta = $mm->getAllMetadata(new \SplFileObject($tmpFilename));
                fclose($tmp);   // will delete the tmp file

                $file->setMetadata(
                    $this->metadataNormalizer->normalizeToArray($meta)
                );
                unset($meta, $mm);

                $this->getEntityManager()->flush();
            }
            else {
                throw new StreamCopyException(
                    sprintf("Failed to copy file id:\"%s\" of workspace:\"%s\" (size=%d)",
                        $file->getId(),
                        $file->getWorkspace()->getName(),
                        $file->getSize()
                    )
                );
            }
        }
        else {
            throw new CreateTemporaryFileException();
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $fileId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $fileId,
        ]);
    }
}
