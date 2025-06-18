<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618135356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update object_type in access_control_entry table from rendition_class and attribute_class to rendition_policy and attribute_policy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE access_control_entry SET object_type = :new WHERE object_type = :old', [
            'new' => 'attribute_policy',
            'old' => 'attribute_class',
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE access_control_entry SET object_type = :new WHERE object_type = :old', [
            'old' => 'attribute_policy',
            'new' => 'attribute_class',
        ]);
    }
}
