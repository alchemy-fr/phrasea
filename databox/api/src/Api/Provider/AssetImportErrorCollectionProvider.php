<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\QueryPaginationTrait;
use App\Entity\Core\AssetImport;
use App\Entity\Core\AssetImportError;
use App\Security\Voter\AbstractVoter;

/**
 * The errors of an import, by line, to whoever can read the import.
 */
final class AssetImportErrorCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use QueryPaginationTrait;

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $import = DoctrineUtil::findStrict($this->em, AssetImport::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $import);

        $queryBuilder = $this->em->getRepository(AssetImportError::class)
            ->createQueryBuilder('t')
            ->andWhere('t.import = :import')
            ->setParameter('import', $import->getId())
            ->addOrderBy('t.line', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        return self::paginateQuery($operation, $queryBuilder);
    }
}
