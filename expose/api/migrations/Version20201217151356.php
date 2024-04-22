<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201217151356 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE asset ALTER size TYPE BIGINT');
        $this->addSql('ALTER TABLE asset ALTER size DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER size TYPE BIGINT');
        $this->addSql('ALTER TABLE sub_definition ALTER size DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset ALTER size TYPE INT');
        $this->addSql('ALTER TABLE asset ALTER size DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER size TYPE INT');
        $this->addSql('ALTER TABLE sub_definition ALTER size DROP DEFAULT');
    }
}
