<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\AssetPermissionComputer;
use App\Entity\Core\Attribute;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AttributePostTransformListener implements EventSubscriberInterface
{
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

        foreach ($this->lastAssetPermissions[1] as $key => $value) {
            $document->set($key, $value);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
