<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Images of the entity list values and workspace logos: image_set, attribute_entity.image_id, workspace.logo_id (replaces workspace.logo_file_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image_set (id UUID NOT NULL, svg_data TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, image_file_id UUID DEFAULT NULL, thumbnail_file_id UUID DEFAULT NULL, workspace_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BEFEF58F6DB2EB0 ON image_set (image_file_id)');
        $this->addSql('CREATE INDEX IDX_BEFEF58FB16F1215 ON image_set (thumbnail_file_id)');
        $this->addSql('CREATE INDEX IDX_BEFEF58F82D40A1F ON image_set (workspace_id)');
        $this->addSql('ALTER TABLE image_set ADD CONSTRAINT FK_BEFEF58F6DB2EB0 FOREIGN KEY (image_file_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE image_set ADD CONSTRAINT FK_BEFEF58FB16F1215 FOREIGN KEY (thumbnail_file_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE image_set ADD CONSTRAINT FK_BEFEF58F82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE attribute_entity ADD image_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A323DA5256D FOREIGN KEY (image_id) REFERENCES image_set (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CC96A323DA5256D ON attribute_entity (image_id)');

        // Each existing logo becomes an image holding the logo file (without thumbnail)
        $this->addSql('ALTER TABLE workspace ADD logo_id UUID DEFAULT NULL');
        $this->addSql('UPDATE workspace SET logo_id = gen_random_uuid() WHERE logo_file_id IS NOT NULL');
        $this->addSql('INSERT INTO image_set (id, workspace_id, image_file_id, created_at) SELECT logo_id, id, logo_file_id, NOW() FROM workspace WHERE logo_id IS NOT NULL');
        $this->addSql('ALTER TABLE workspace ADD CONSTRAINT FK_8D940019F98F144A FOREIGN KEY (logo_id) REFERENCES image_set (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D940019F98F144A ON workspace (logo_id)');

        // Drops its foreign key and index along, whatever their names
        $this->addSql('ALTER TABLE workspace DROP logo_file_id');
    }

    public function down(Schema $schema): void
    {
        // An SVG logo has no file: it is lost
        $this->addSql('ALTER TABLE workspace ADD logo_file_id UUID DEFAULT NULL');
        $this->addSql('UPDATE workspace w SET logo_file_id = i.image_file_id FROM image_set i WHERE i.id = w.logo_id');
        $this->addSql('CREATE INDEX IDX_8D940019A7E1931C ON workspace (logo_file_id)');
        $this->addSql('ALTER TABLE workspace ADD CONSTRAINT FK_8D9400195911EFB0 FOREIGN KEY (logo_file_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('ALTER TABLE workspace DROP CONSTRAINT FK_8D940019F98F144A');
        $this->addSql('DROP INDEX UNIQ_8D940019F98F144A');
        $this->addSql('ALTER TABLE workspace DROP logo_id');

        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A323DA5256D');
        $this->addSql('DROP INDEX UNIQ_2CC96A323DA5256D');
        $this->addSql('ALTER TABLE attribute_entity DROP image_id');

        $this->addSql('ALTER TABLE image_set DROP CONSTRAINT FK_BEFEF58F6DB2EB0');
        $this->addSql('ALTER TABLE image_set DROP CONSTRAINT FK_BEFEF58FB16F1215');
        $this->addSql('ALTER TABLE image_set DROP CONSTRAINT FK_BEFEF58F82D40A1F');
        $this->addSql('DROP TABLE image_set');
    }
}
