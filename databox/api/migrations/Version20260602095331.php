<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602095331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to template_attribute.definition_id foreign key constraint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT FK_3329994DD11EA911');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT FK_3329994DD11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT fk_3329994dd11ea911');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT fk_3329994dd11ea911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
