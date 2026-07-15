<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Entity\Core\FileMetadata;
use App\Migrations\AbstractServiceContainerMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713140456 extends AbstractServiceContainerMigration
{
    public function getDescription(): string
    {
        return 'Add doc_unique_id to file table and checksum to file_metadata table, and create indexes for performance';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file ADD doc_unique_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN file.doc_unique_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1FDE6FDF9A ON file (workspace_id, checksum)');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1F1F1EBF22 ON file (workspace_id, doc_unique_id)');
        $this->addSql('ALTER TABLE file_metadata ADD checksum VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_8C9F361082D40A1FDE6FDF9A');
        $this->addSql('DROP INDEX IDX_8C9F361082D40A1F1F1EBF22');
        $this->addSql('ALTER TABLE file DROP doc_unique_id');
        $this->addSql('ALTER TABLE file_metadata DROP checksum');
    }

    public function postUp(Schema $schema): void
    {
        DeferredIndexListener::disable();

        $em = $this->getEntityManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        /** @var FileMetadata[] $items */
        $items = $em->createQueryBuilder()
            ->select('d')
            ->from(FileMetadata::class, 'd')
            ->getQuery()
            ->toIterable();

        $i = 0;
        $batchSize = 20;
        foreach ($items as $d) {
            if (null === $d->getChecksum()) {
                $d->setMetadata($d->getMetadata());
                $em->persist($d);

                if (($i % $batchSize) === 0) {
                    $em->flush();
                    $em->clear();
                    ++$i;
                }

                unset($d);
            }
        }

        if ($i > 0) {
            $em->flush();
        }
    }
}
