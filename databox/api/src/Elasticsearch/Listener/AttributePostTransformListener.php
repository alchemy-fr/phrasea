<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Attribute\Type\EntityAttributeType;
use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\Listener\Dto\AssetPermissionsDTO;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Elastica\Document;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Ramsey\Uuid\Rfc4122\UuidV4;
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
        private readonly AttributeEntityRepository $attributeEntityRepository,
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

        $definition = $attribute->getDefinition();
        if (EntityAttributeType::NAME === $definition->getFieldType()) {
            if (!$this->resolveEntity($attribute, $document)) {
                return;
            }
        }

        $assetId = $asset->getId();
        if (null === $this->lastAssetPermissions || $this->lastAssetPermissions[0] !== $assetId) {
            $this->lastAssetPermissions = [$assetId, $this->assetPermissionComputer->getAssetPermissionFields($asset)];
        }

        foreach ($this->lastAssetPermissions[1]->toDocument() as $key => $value) {
            $document->set($key, $value);
        }
    }

    private function resolveEntity(Attribute $attribute, Document $document): bool
    {
        if ($attribute->getDefinition()->getEntityList()) {
            if (UuidV4::isValid($attribute->getValue())) {
                /** @var AttributeEntity $entity */
                $entity = $this->attributeEntityRepository->find($attribute->getValue());
                if ($entity) {
                    $document->set('entityId', $attribute->getValue());
                    $document->set('suggestion', $entity->getValue());

                    return true;
                }
            }
        }

        return false;
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
