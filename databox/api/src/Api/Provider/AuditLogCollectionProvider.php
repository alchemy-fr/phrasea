<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The audit log, the last entry first (admins only, see the resource security).
 *
 * Queried in SQL: the audit log is not an asset listing, it holds no asset right to apply.
 */
final readonly class AuditLogCollectionProvider implements ProviderInterface
{
    use ParameterValuesTrait;

    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private EntityManagerInterface $em,
        private AuditLogManager $auditLogManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Paginator
    {
        $limit = max(1, min(self::MAX_LIMIT, (int) self::getParameterValue($operation, 'limit', self::DEFAULT_LIMIT)));
        $page = max(1, (int) self::getParameterValue($operation, 'page', 1));

        $queryBuilder = $this->em->createQueryBuilder()
            ->select('l')
            ->from(AuditLog::class, 'l')
            ->addOrderBy('l.date', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $this->applyFilters($queryBuilder, $operation);

        return new Paginator(new DoctrinePaginator($queryBuilder->getQuery(), false));
    }

    private function applyFilters(QueryBuilder $queryBuilder, Operation $operation): void
    {
        $actions = self::getParameterValue($operation, 'action');
        if (null !== $actions) {
            $actions = array_filter(array_map(
                static fn (mixed $key): ?int => \is_string($key) ? AuditLogActionEnum::tryFromKey($key)?->value : null,
                \is_array($actions) ? $actions : [$actions],
            ));
            $this->restrict($queryBuilder, empty($actions) ? null : 'l.action IN (:actions)', ['actions' => array_values($actions)]);
        }

        $actorType = self::getParameterValue($operation, 'actorType');
        if (\is_string($actorType)) {
            $actorType = AuditActorTypeEnum::tryFromKey($actorType);
            $this->restrict($queryBuilder, null !== $actorType ? 'l.actorType = :actorType' : null, ['actorType' => $actorType?->value]);
        }

        $objectType = self::getParameterValue($operation, 'objectType');
        if (\is_string($objectType)) {
            $objectType = $this->auditLogManager->getObjectTypeKeys()[$objectType] ?? null;
            $this->restrict($queryBuilder, null !== $objectType ? 'l.objectType = :objectType' : null, ['objectType' => $objectType]);
        }

        foreach (['objectId', 'clientId'] as $field) {
            $value = self::getParameterValue($operation, $field);
            if (\is_string($value) && '' !== $value) {
                $this->restrict($queryBuilder, sprintf('l.%1$s = :%1$s', $field), [$field => $value]);
            }
        }

        $userId = self::getParameterValue($operation, 'userId');
        if (\is_string($userId) && '' !== $userId) {
            $this->restrict($queryBuilder, 'l.userId = :userId OR l.impersonatorId = :userId', ['userId' => $userId]);
        }

        foreach (['workspaceId', 'correlationId'] as $field) {
            $value = self::getParameterValue($operation, $field);
            if (\is_string($value) && '' !== $value) {
                $this->restrict($queryBuilder, Uuid::isValid($value) ? sprintf('l.%1$s = :%1$s', $field) : null, [$field => $value]);
            }
        }

        foreach (['after' => '>=', 'before' => '<='] as $bound => $operator) {
            $value = self::getParameterValue($operation, 'date['.$bound.']');
            if (\is_string($value) && '' !== $value) {
                try {
                    $date = new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    throw new BadRequestHttpException(sprintf('Invalid date "%s"', $value), $e);
                }
                $this->restrict($queryBuilder, sprintf('l.date %s :%s', $operator, $bound), [$bound => $date]);
            }
        }
    }

    /**
     * @param string|null $condition null when the filter value matches nothing
     */
    private function restrict(QueryBuilder $queryBuilder, ?string $condition, array $parameters): void
    {
        if (null === $condition) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->andWhere($condition);
        foreach ($parameters as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }
    }
}
