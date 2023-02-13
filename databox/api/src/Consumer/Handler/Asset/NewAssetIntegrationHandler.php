<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class NewAssetIntegrationHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_integration';

    private IntegrationManager $integrationManager;

    public function __construct(IntegrationManager $integrationManager)
    {
        $this->integrationManager = $integrationManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];
        $workspaceIntegrationId = $payload['wsI'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        $workspaceIntegration = $em->find(WorkspaceIntegration::class, $workspaceIntegrationId);
        if (!$workspaceIntegration instanceof WorkspaceIntegration) {
            throw new ObjectNotFoundForHandlerException(WorkspaceIntegration::class, $workspaceIntegrationId, __CLASS__);
        }

        $this->integrationManager->handleAssetIntegration($asset, $workspaceIntegration);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $assetId, string $workspaceIntegrationId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $assetId,
            'wsI' => $workspaceIntegrationId,
        ]);
    }
}