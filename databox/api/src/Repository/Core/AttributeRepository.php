<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[AsEventListener(KernelEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'reset', priority: -5)]
class AttributeRepository extends ServiceEntityRepository
{
    private array $attributeCache = [];

    public function __construct(
        ManagerRegistry $registry,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $kernelEnv,
    ) {
        parent::__construct($registry, Attribute::class);
    }

    /**
     * @return string[]
     */
    public function getDuplicates(Attribute $attribute): array
    {
        $definition = $attribute->getDefinition();

        $queryBuilder = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.definition = :definition')
            ->andWhere('a.asset = :asset')
            ->andWhere('a.id != :id')
            ->setParameter('definition', $definition->getId())
            ->setParameter('asset', $attribute->getAsset()->getId())
            ->setParameter('id', $attribute->getId());

        if ($definition->isTranslatable()) {
            $queryBuilder
                ->andWhere('a.locale = :locale')
                ->setParameter('locale', $attribute->getLocale())
            ;
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function getAssetAttributes(string $assetId): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.asset = :asset')
            ->andWhere('d.enabled = true')
            ->setParameter('asset', $assetId)
            ->innerJoin('a.definition', 'd')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.value', 'ASC')
            ->addOrderBy('a.id', 'ASC')
        ;

        $this->restrictTranslatableFields($queryBuilder);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getCachedAssetAttributes(string $assetId): array
    {
        if (isset($this->attributeCache[$assetId])) {
            return $this->attributeCache[$assetId];
        }

        return $this->attributeCache[$assetId] = $this->getAssetAttributes($assetId);
    }

    /**
     * @return Attribute[]
     */
    public function getAssetAttributeIdsIterator(string $assetId): iterable
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a.id')
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $assetId)
            ->getQuery()
            ->toIterable();
    }

    public function getESQueryBuilder(): QueryBuilder
    {
        $types = array_map(
            fn (AttributeTypeInterface $type): string => $type::getName(),
            array_filter(
                $this->attributeTypeRegistry->getTypes(),
                fn (AttributeTypeInterface $type): bool => $type->supportsSuggest()
            )
        );

        $queryBuilder = $this
            ->createQueryBuilder('t');

        if ('test' !== $this->kernelEnv) {
            // SQLite does not find asset_id in the wrapped query
            $queryBuilder->addOrderBy('t.asset', 'ASC');
        }

        $queryBuilder
            ->addOrderBy('t.id', 'ASC')
            ->innerJoin('t.definition', 'd')
            ->andWhere('d.enabled = true')
            ->andWhere('d.fieldType IN (:types)')
            ->setParameter('types', $types);

        $this->restrictTranslatableFields($queryBuilder, 't');

        return $queryBuilder;
    }

    private function restrictTranslatableFields(QueryBuilder $queryBuilder, $rootAlias = 'a'): void
    {
        $queryBuilder->andWhere(sprintf('d.translatable = true OR %1$s.locale IS NULL OR %1$s.locale = \'%2$s\'', $rootAlias, AttributeInterface::NO_LOCALE));
    }

    public function deleteByAttributeEntity(string $entityId, string $workspaceId, string $entityListId): void
    {
        $expr = $this->_em->getExpressionBuilder();
        $this
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere($expr->in(
                't.id',
                $this
                    ->createQueryBuilder('a')
                    ->select('a.id')
                    ->innerJoin('a.definition', 'd')
                    ->andWhere('d.workspace = :workspace')
                    ->andWhere('d.fieldType = :t')
                    ->andWhere('d.entityList = :etype')
                    ->andWhere('a.value = :id')
                    ->getDQL()
            ))
            ->setParameter('workspace', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('etype', $entityListId)
            ->setParameter('id', $entityId)
            ->getQuery()
            ->execute();
    }

    public function reset(): void
    {
        $this->attributeCache = [];
    }
}
