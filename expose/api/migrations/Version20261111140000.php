<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Share links of the publications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE publication_share (id UUID NOT NULL, publication_id UUID NOT NULL, name VARCHAR(255) DEFAULT NULL, token VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AD8DBD65F37A13B ON publication_share (token)');
        $this->addSql('CREATE INDEX IDX_AD8DBD638B217A7 ON publication_share (publication_id)');
        $this->addSql('ALTER TABLE publication_share ADD CONSTRAINT FK_AD8DBD638B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publication_share');
    }
}
