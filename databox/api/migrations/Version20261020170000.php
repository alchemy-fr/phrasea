<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parade: Keycloak settings of the Parade integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parade ADD settings JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE parade ADD clients_report JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parade DROP settings');
        $this->addSql('ALTER TABLE parade DROP clients_report');
    }
}
