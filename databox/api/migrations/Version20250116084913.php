<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250116084913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_title_attribute DROP CONSTRAINT FK_D86B14D3D11EA911');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT FK_D86B14D3D11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_title_attribute DROP CONSTRAINT fk_d86b14d3d11ea911');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT fk_d86b14d3d11ea911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
