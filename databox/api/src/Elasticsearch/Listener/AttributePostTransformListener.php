<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\AssetPermissionsDTO;
use App\Entity\Core\Attribute;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[AsEventListener(KernelEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'reset', priority: -5)]
final class AttributePostTransformListener implements EventSubscriberInterface
{
    /**
     * @var array{0: string, 1: AssetPermissionsDTO}|null
     */
    private ?array $lastAssetPermissions = null;

    public function __construct(
        private readonly AssetPermissionComputer $assetPermissionComputer,
    ) {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Attribute $attribute */
        if (!($attribute = $event->getObject()) instanceof Attribute) {
            return;
        }

        $document = $event->getDocument();
        $asset = $attribute->getAsset();

        $assetId = $asset->getId();
        if (null === $this->lastAssetPermissions || $this->lastAssetPermissions[0] !== $assetId) {
            $this->lastAssetPermissions = [$assetId, $this->assetPermissionComputer->getAssetPermissionFields($asset)];
        }

        foreach ($this->lastAssetPermissions[1]->toDocument() as $key => $value) {
            $document->set($key, $value);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }

    public function reset(): void
    {
        $this->lastAssetPermissions = null;
    }
}
