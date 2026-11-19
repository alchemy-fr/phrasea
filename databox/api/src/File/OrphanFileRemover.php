<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Entity\Discussion\Message;
use App\Entity\Integration\IntegrationData;
use App\Security\Voter\FileVoter;
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
                    ->setParameter('id', $fileId)
                    ->setMaxResults(1)
                    ->executeQuery();

                if ($results->fetchOne()) {
                    return false;
                }
            }
        }

        if ($this->isReferencedByValue($fileId)) {
            return false;
        }

        $this->em->remove($file);
        $this->em->flush();

        return true;
    }

    /**
     * References without a foreign key: message attachments, which hold the file
     * ID in their JSON, and integration outputs, which hold it as the data value.
     */
    private function isReferencedByValue(string $fileId): bool
    {
        $integrationOutput = $this->em->createQueryBuilder()
            ->select('d.id')
            ->from(IntegrationData::class, 'd')
            ->andWhere('d.name = :name')
            ->andWhere('d.value = :id')
            ->andWhere(sprintf('LENGTH(d.value) = %d', IntegrationData::INDEXED_VALUE_LENGTH))
            ->setParameter('name', FileVoter::INTEGRATION_OUTPUT_DATA_NAME)
            ->setParameter('id', $fileId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (null !== $integrationOutput) {
            return true;
        }

        $cnx = $this->em->getConnection();
        $metadata = $this->em->getClassMetadata(Message::class);

        return false !== $cnx->createQueryBuilder()
            ->select('1')
            ->from($cnx->quoteIdentifier($metadata->getTableName()))
            ->andWhere(sprintf('CAST(%s AS TEXT) LIKE :pattern', $cnx->quoteIdentifier($metadata->getColumnName('attachments'))))
            ->setParameter('pattern', '%'.$fileId.'%')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }

    private function getColumns(): array
    {
        if (null === $this->columns) {
            /** @var ClassMetadata[] $allMeta */
            $allMeta = $this->em->getMetadataFactory()
                ->getAllMetadata();

            $this->columns = [];
            foreach ($allMeta as $metadata) {
                if (FileDuplicate::class === $metadata->getName()) {
                    // Duplicate links must not prevent orphan removal; they are cleaned up by DB cascade.
                    continue;
                }

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
