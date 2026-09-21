<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen file.path to TEXT: remote (presigned) source URLs regularly exceed 255 characters and aborted the asset creation with a driver error';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ALTER COLUMN path TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ALTER COLUMN path TYPE VARCHAR(255)');
    }
}
