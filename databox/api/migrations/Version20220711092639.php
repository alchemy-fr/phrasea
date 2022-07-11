<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220711092639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file ALTER size TYPE BIGINT');
        $this->addSql('ALTER TABLE file ALTER size DROP DEFAULT');
        $this->addSql('ALTER TABLE populate_pass ALTER document_count TYPE BIGINT');
        $this->addSql('ALTER TABLE populate_pass ALTER document_count DROP DEFAULT');
        $this->addSql('ALTER TABLE populate_pass ALTER progress TYPE BIGINT');
        $this->addSql('ALTER TABLE populate_pass ALTER progress DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE file ALTER size TYPE INT');
        $this->addSql('ALTER TABLE file ALTER size DROP DEFAULT');
        $this->addSql('ALTER TABLE populate_pass ALTER document_count TYPE INT');
        $this->addSql('ALTER TABLE populate_pass ALTER document_count DROP DEFAULT');
        $this->addSql('ALTER TABLE populate_pass ALTER progress TYPE INT');
        $this->addSql('ALTER TABLE populate_pass ALTER progress DROP DEFAULT');
    }
}
