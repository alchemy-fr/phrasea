<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211104154911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DELETE FROM
    sub_definition a
        USING sub_definition b
WHERE
    a.id < b.id
    AND a.specification_id = b.specification_id
    AND a.asset_id = b.asset_id
;');
        $this->addSql('CREATE UNIQUE INDEX uniq_sub_def ON sub_definition (specification_id, asset_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX uniq_sub_def');
    }
}
