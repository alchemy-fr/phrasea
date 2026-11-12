<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Form schema format: LiForm (legacy) or form builder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE form_schema ADD format VARCHAR(20) DEFAULT \'liform\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE form_schema DROP format');
    }
}
