<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the form data of the last commit of the upload links, which every holder of a link could read';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE upload_link DROP last_form_data');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE upload_link ADD last_form_data JSON DEFAULT NULL');
    }
}
