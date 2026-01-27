<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120174525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enabled field to terms config in publication and publication profile';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            'publication_profile',
            'publication',
        ] as $table) {
            foreach (['config_terms', 'config_download_terms'] as $prefix) {
                $this->addSql(sprintf('ALTER TABLE %1$s ADD %2$s_enabled BOOLEAN DEFAULT NULL', $table, $prefix));
                $this->addSql(sprintf('UPDATE %1$s SET %2$s_enabled = true WHERE %2$s_text IS NOT NULL OR %2$s_url IS NOT NULL', $table, $prefix));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication DROP config_terms_enabled');
        $this->addSql('ALTER TABLE publication DROP config_download_terms_enabled');
        $this->addSql('ALTER TABLE publication_profile DROP config_terms_enabled');
        $this->addSql('ALTER TABLE publication_profile DROP config_download_terms_enabled');
    }
}
