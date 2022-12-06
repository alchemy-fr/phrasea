<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Asset\FileFetcher;
use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class ReadMetadataHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'read_file_metadata';
    private MetadataNormalizer $metadataNormalizer;
    private FileFetcher $fileFetcher;

    public function __construct(MetadataNormalizer $metadataNormalizer, FileFetcher $fileFetcher)
    {
        $this->metadataNormalizer = $metadataNormalizer;
        $this->fileFetcher = $fileFetcher;
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

        $fetchedFilePath = $this->fileFetcher->getFile($file);
        try {
            $mm = new MetadataManipulator();
            $meta = $mm->getAllMetadata(new \SplFileObject($fetchedFilePath));

            $file->setMetadata(
                $this->metadataNormalizer->normalize($meta)
            );
            unset($meta, $mm);

            $em = $this->getEntityManager();
            $em->persist($file);
            $em->flush();
        } finally {
            if($fetchedFilePath) {
                @unlink($fetchedFilePath);
            }
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
