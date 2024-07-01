<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Asset\Attribute\AttributesResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class AssetPostTransformListener implements EventSubscriberInterface
{
    public function __construct(
        private AssetPermissionComputer $assetPermissionComputer,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private FieldNameResolver $fieldNameResolver,
        private AttributesResolver $attributesResolver,
        private EntityManagerInterface $em
    ) {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $permFields = $this->assetPermissionComputer->getAssetPermissionFields($asset);
        foreach ($permFields as $key => $value) {
            $document->set($key, $value);
        }

        $document->set('attributes', $this->compileAttributes($asset));
        $document->set('renditions', $this->compileRenditions($asset));
    }

    private function compileRenditions(Asset $asset): array
    {
        $renditionsDefinitions = $this->em->createQueryBuilder()
            ->select('rd.id')
            ->from(AssetRendition::class, 'r')
            ->innerJoin(RenditionDefinition::class, 'rd', Join::WITH, 'rd.id = r.definition')
            ->andWhere('r.asset = :id')
            ->setParameter('id', $asset->getId())
            ->getQuery()
            ->toIterable();

        $renditions = [];
        foreach ($renditionsDefinitions as $row) {
            $renditions[] = (string) $row['id'];
        }

        return $renditions;
    }

    private function compileAttributes(Asset $asset): array
    {
        $data = [];

        $attributeIndex = $this->attributesResolver->resolveAssetAttributesList($asset, false);

        foreach ($attributeIndex->getDefinitions() as $definitionIndex) {
            foreach ($definitionIndex->getLocales() as $l => $a) {
                $definition = $definitionIndex->getDefinition();
                $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

                $v = null;
                if ($definition->isMultiple()) {
                    if (!empty($a)) {
                        $v = array_map(fn (Attribute $v): string => $type->normalizeElasticsearchValue($v->getValue()), $a);
                    }
                } else {
                    $v = $a->getValue();
                    if (null !== $v) {
                        $v = $type->normalizeElasticsearchValue($v);
                    }
                }

                if (
                    null !== $v
                    && (!is_array($v) || !empty($v))
                ) {
                    $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
                    $data[$l][$fieldName] = $v;
                }
            }
        }

        return $data;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
