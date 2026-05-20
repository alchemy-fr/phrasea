<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504145058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate ACL';
    }

    public function up(Schema $schema): void
    {
        foreach (['collection', 'workspace'] as $type) {
            $this->addSql('UPDATE access_control_entry SET mask = mask | 2 | 1024 | 2048 WHERE object_type = :type AND mask & 4 = 4', [
                'type' => $type,
            ]);
            $this->addSql('UPDATE access_control_entry SET mask = mask | 16384 WHERE object_type = :type AND mask & 32 = 32', [
                'type' => $type,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
