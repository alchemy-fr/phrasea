<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileCopier;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class CopyFileToRenditionHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'copy_file_to_rendition';

    public function __construct(private readonly FileCopier $fileCopier)
    {
    }

    public static function createEvent(string $renditionId, string $fileId): EventMessage
    {
        $payload = [
            'renditionId' => $renditionId,
            'fileId' => $fileId,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $renditionId = $payload['renditionId'];
        $fileId = $payload['fileId'];

        $em = $this->getEntityManager();
        $rendition = $em->find(AssetRendition::class, $renditionId);
        if (!$rendition instanceof AssetRendition) {
            throw new ObjectNotFoundForHandlerException(AssetRendition::class, $renditionId, self::class);
        }

        $file = $em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $fileId, self::class);
        }

        $copy = $this->fileCopier->copyFile($file, $rendition->getAsset()->getWorkspace());

        $rendition->setFile($copy);

        $em->persist($rendition);
        $em->flush();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
