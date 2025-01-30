<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

final class OrphanFileRemover
{
    private ?array $columns = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return bool Whether the file was removed or not
     */
    public function removeIfOrphan(string $fileId): bool
    {
        $columns = $this->getColumns();

        $file = $this->em->find(File::class, $fileId);
        if (!$file instanceof File) {
            return false;
        }

        $cnx = $this->em->getConnection();
        foreach ($columns as $table => $cols) {
            foreach ($cols as $col) {
                $results = $cnx
                    ->createQueryBuilder()
                    ->addSelect('1')
                    ->from($cnx->quoteIdentifier($table))
                    ->andWhere(sprintf('%s = :id', $cnx->quoteIdentifier($col)))
                    ->setParameters([
                        'id' => $fileId,
                    ])
                    ->setMaxResults(1)
                    ->executeQuery();

                if ($results->fetchOne()) {
                    return false;
                }
            }
        }

        $this->em->remove($file);
        $this->em->flush();

        return true;
    }

    private function getColumns(): array
    {
        if (null === $this->columns) {
            /** @var ClassMetadata[] $allMeta */
            $allMeta = $this->em->getMetadataFactory()
                ->getAllMetadata();

            $this->columns = [];
            foreach ($allMeta as $metadata) {
                foreach ($metadata->getAssociationMappings() as $mapping) {
                    if ($metadata->isSingleValuedAssociation($mapping['fieldName'])
                        && !$metadata->isAssociationInverseSide($mapping['fieldName'])) {
                        if (File::class === $mapping['targetEntity']) {
                            $tableName = $metadata->getTableName();
                            $this->columns[$tableName] ??= [];
                            $this->columns[$tableName][] = $metadata->getSingleAssociationJoinColumnName($mapping['fieldName']);
                        }
                    }
                }
            }
        }

        return $this->columns;
    }
}
