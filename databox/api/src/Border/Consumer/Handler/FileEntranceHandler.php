<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use App\Asset\FileUrlResolver;
use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Consumer\Handler\File\NewAssetFromBorderHandler;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class FileEntranceHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'file_entrance';

    private BorderManager $borderManager;
    private EventProducer $eventProducer;
    private FileUrlResolver $fileUrlResolver;

    public function __construct(
        BorderManager $borderManager,
        EventProducer $eventProducer,
        FileUrlResolver $fileUrlResolver
    ) {
        $this->borderManager = $borderManager;
        $this->eventProducer = $eventProducer;
        $this->fileUrlResolver = $fileUrlResolver;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();

        $em = $this->getEntityManager();
        $workspaceId = $payload['workspaceId'];
        $workspace = $em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new ObjectNotFoundForHandlerException(Workspace::class, $workspaceId, __CLASS__);
        }

        $file = $this->getEntityManager()->getRepository(File::class)->find($payload['fileId']);

        $inputFile = new InputFile(
            $file->getOriginalName(),
            $file->getType(),
            $file->getSize(),
            $this->fileUrlResolver->resolveUrl($file),
        );

        $file = $this->borderManager->acceptFile($inputFile, $workspace);

        if ($file instanceof File) {
            $this->eventProducer->publish(NewAssetFromBorderHandler::createEvent(
                $payload['userId'],
                $file->getId(),
                $payload['collections'],
                $payload['title'] ?? null,
                $inputFile->getName(),
                null,
                $payload['locale'] ?? null
            ));
        }
    }

    public static function createEvent(
        string $userId,
        string $workspaceId,
        string $fileId,
        array $collections,
        ?string $title = null,
        ?array $formData = null,
        ?string $locale = null

    ): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'userId' => $userId,
            'workspaceId' => $workspaceId,
            'fileId' => $fileId,
            'collections' => $collections,
            'title' => $title,
            'formData' => $formData,
            'locale' => $locale,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
