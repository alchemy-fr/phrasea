<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shares flag their rendition selection (share.renditions_restricted), which deleted definitions can no longer widen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share ADD renditions_restricted BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE share SET renditions_restricted = true WHERE id IN (SELECT share_id FROM share_rendition_definition)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share DROP renditions_restricted');
    }
}
