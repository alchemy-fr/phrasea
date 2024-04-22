<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220802161826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('INSERT INTO target (id, name, target_url, target_access_token, created_at) VALUES (:id, :name, :target_url, :target_access_token, :created_at)', [
            'id' => Uuid::uuid4(),
            'name' => 'Default',
            'target_url' => getenv('ASSET_CONSUMER_COMMIT_URI'),
            'target_access_token' => getenv('ASSET_CONSUMER_ACCESS_TOKEN'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DELETE FROM target WHERE name = \'Default\'');
    }
}
