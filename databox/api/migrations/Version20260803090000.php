<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace saved_search.public boolean with a privacy status (0=secret, 1=private, 2=public)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search ADD privacy SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE saved_search SET privacy = CASE WHEN public = true THEN 2 ELSE 0 END');
        $this->addSql('ALTER TABLE saved_search DROP public');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search ADD public BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE saved_search SET public = (privacy = 2)');
        $this->addSql('ALTER TABLE saved_search DROP privacy');
    }
}
