<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parade: parade_login_client';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parade_login_client (id UUID NOT NULL, page VARCHAR(63) NOT NULL, client_id VARCHAR(255) NOT NULL, attributes JSON NOT NULL, status VARCHAR(20) NOT NULL, error TEXT DEFAULT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX parade_login_client_page_uniq ON parade_login_client (page)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parade_login_client');
    }
}
