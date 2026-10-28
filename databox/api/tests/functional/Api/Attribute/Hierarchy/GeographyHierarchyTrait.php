<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute\Hierarchy;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;

/**
 * The geography of the study:
 *
 *   Monde (root, 0)
 *   └─ Europe (1)
 *      ├─ France (2)
 *      │  ├─ Île-de-France (3) ─ Paris (4) ─ 16e (5) ─ Rue Victor Hugo (6)
 *      │  └─ Indre-et-Loire (3) ─ Tours (4) ─ [placeholder] (5) ─ Rue Victor Hugo (6)
 *      ├─ Italie (2) ─ Latium (3) ─ Rome (4)
 *      └─ Belgique (2)
 */
trait GeographyHierarchyTrait
{
    protected EntityList $geo;

    /**
     * @var array<string, AttributeEntity>
     */
    protected array $e = [];

    /**
     * @var array<string, AttributeDefinition>
     */
    protected array $defs = [];

    protected function createGeography(): void
    {
        $em = self::getEntityManager();
        $this->geo = $this->createEntityList(['name' => 'Géographie']);

        $this->e['monde'] = $this->node('Monde', null);
        $this->geo->setRootEntity($this->e['monde']);
        $this->e['europe'] = $this->node('Europe', 'monde');
        $this->e['france'] = $this->node('France', 'europe');
        $this->e['idf'] = $this->node('Île-de-France', 'france');
        $this->e['paris'] = $this->node('Paris', 'idf');
        $this->e['16e'] = $this->node('16e', 'paris');
        $this->e['rvh_paris'] = $this->node('Rue Victor Hugo', '16e');
        $this->e['indre'] = $this->node('Indre-et-Loire', 'france');
        $this->e['tours'] = $this->node('Tours', 'indre');
        $this->e['tours_none'] = $this->node('—', 'tours', placeholder: true);
        $this->e['rvh_tours'] = $this->node('Rue Victor Hugo', 'tours_none');
        $this->e['italie'] = $this->node('Italie', 'europe');
        $this->e['latium'] = $this->node('Latium', 'italie');
        $this->e['rome'] = $this->node('Rome', 'latium');
        $this->e['belgique'] = $this->node('Belgique', 'europe');
        $em->persist($this->geo);
        $em->flush();
    }

    /**
     * @param array<string, bool> $multiple by definition key
     */
    protected function createGeographyDefinitions(array $multiple = []): void
    {
        foreach (['continent' => 1, 'pays' => 2, 'region' => 3, 'ville' => 4, 'arrondissement' => 5, 'rue' => 6] as $key => $depth) {
            $definition = $this->createAttributeDefinition([
                'name' => ucfirst($key),
                'slug' => $key,
                'type' => EntityAttributeType::NAME,
                'list' => $this->geo,
                'multiple' => $multiple[$key] ?? false,
                'no_flush' => true,
            ]);
            $definition->setEntityHierarchyDepth($depth);
            $definition->setAutoFillParents(true);
            $this->defs[$key] = $definition;
        }
        self::getEntityManager()->flush();
    }

    private function node(string $value, ?string $parentKey, bool $placeholder = false): AttributeEntity
    {
        $entity = $this->createEntity($this->geo, $value, ['no_flush' => true]);
        $entity->setPlaceholder($placeholder);
        if (null !== $parentKey) {
            $parent = $this->e[$parentKey];
            $entity->setParent($parent);
            $entity->setHierarchyDepth($parent->getHierarchyDepth() + 1);
            $entity->setPosition($parent->getChildren()->count());
            $parent->getChildren()->add($entity);
        } else {
            $entity->setHierarchyDepth(0);
        }

        return $entity;
    }
}
