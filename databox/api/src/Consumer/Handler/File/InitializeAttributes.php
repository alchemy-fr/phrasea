<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\Attribute\InitialResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class InitializeAttributes extends AbstractEntityManagerHandler
{
    const EVENT = 'initialize_attributes';
    private InitialResolver $initialValueResolver;

    public function __construct(InitialResolver $initialValueResolver)
    {
        $this->initialValueResolver = $initialValueResolver;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $assetId = $payload['id'];

        $em = $this->getEntityManager();

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, __CLASS__);
        }

        $workspace = $asset->getWorkspace();
        if (!$workspace instanceof Workspace) {
            throw new ObjectNotFoundForHandlerException(Workspace::class, $asset->getWorkspaceId(), __CLASS__);
        }

        $attributes = $this->initialValueResolver->resolveInitialAttributes($asset);

        foreach ($attributes as $attribute) {
            $em->persist($attribute);
        }
        $em->flush();
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
