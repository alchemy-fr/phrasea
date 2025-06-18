<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618134458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_class RENAME TO attribute_policy
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_class RENAME TO rendition_policy
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_policy RENAME TO rendition_class
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_policy RENAME TO attribute_class
        SQL);
    }
}
