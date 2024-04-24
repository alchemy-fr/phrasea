<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240424150209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE asset SET web_vtt = NULL');
        $this->addSql('ALTER TABLE asset ALTER web_vtt TYPE JSON USING web_vtt::json');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset ALTER web_vtt TYPE TEXT');
    }
}
