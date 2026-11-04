<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901151803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the file_duplicate table (source of truth for the duplicates detected by file analysis)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file_duplicate (id UUID NOT NULL, file_id UUID NOT NULL, duplicate_file_id UUID NOT NULL, analyzer VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9C483E6D93CB796C ON file_duplicate (file_id)');
        $this->addSql('CREATE INDEX file_duplicate_dup_idx ON file_duplicate (duplicate_file_id)');
        $this->addSql('CREATE UNIQUE INDEX file_duplicate_uniq ON file_duplicate (file_id, duplicate_file_id, analyzer)');
        $this->addSql('COMMENT ON COLUMN file_duplicate.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_duplicate.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_duplicate.duplicate_file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_duplicate.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE file_duplicate ADD CONSTRAINT FK_9C483E6D93CB796C FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE file_duplicate ADD CONSTRAINT FK_9C483E6DE04EAA1C FOREIGN KEY (duplicate_file_id) REFERENCES file (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file_duplicate DROP CONSTRAINT FK_9C483E6D93CB796C');
        $this->addSql('ALTER TABLE file_duplicate DROP CONSTRAINT FK_9C483E6DE04EAA1C');
        $this->addSql('DROP TABLE file_duplicate');
    }
}
