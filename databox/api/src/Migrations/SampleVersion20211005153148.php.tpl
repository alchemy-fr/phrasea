<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Document;
use App\Elasticsearch\Listener\DeferredIndexListener;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211005153148 extends AbstractServiceContainerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('...');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }

    public function postUp(Schema $schema): void
    {
        DeferredIndexListener::disable();

        $em = $this->getEntityManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        /** @var Document[] $documents */
        $documents = $em->createQueryBuilder()
            ->select('d.id')
            ->from(Document::class, 'd')
            ->getQuery()
            ->toIterable();

        foreach ($documents as $doc) {
            $document = $em->find(Document::class, $doc['id']);
            // ... do stuff
            $em->persist($document);

            $em->flush();
            $em->clear();
            gc_collect_cycles();
        }
    }
}
