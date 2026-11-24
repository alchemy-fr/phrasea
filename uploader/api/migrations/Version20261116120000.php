<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upload links: what is recorded of their creator, mandatory expiration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE upload_link ADD creator_groups JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE upload_link ADD creator_roles JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE upload_link ALTER creator_groups DROP DEFAULT');
        $this->addSql('ALTER TABLE upload_link ALTER creator_roles DROP DEFAULT');

        // The default maximum lifetime (UPLOAD_LINK_MAX_TTL), whatever the configured one
        $this->addSql("UPDATE upload_link SET expires_at = NOW() + INTERVAL '90 days' WHERE expires_at IS NULL");
        $this->addSql('ALTER TABLE upload_link ALTER expires_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE upload_link ALTER expires_at DROP NOT NULL');
        $this->addSql('ALTER TABLE upload_link DROP creator_roles');
        $this->addSql('ALTER TABLE upload_link DROP creator_groups');
    }
}
