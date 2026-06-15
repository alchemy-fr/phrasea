<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610134844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove asset name';
    }

    public function preUp(Schema $schema): void
    {
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE attribute SET status = 0 WHERE status IS NULL');
        $this->addSql('ALTER TABLE attribute ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD name_priority SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD fill_from_name BOOLEAN NOT NULL DEFAULT \'false\'');
        $this->addSql('ALTER TABLE attribute_definition ALTER COLUMN fill_from_name DROP DEFAULT');
        $this->addSql('CREATE INDEX fill_from_name_idx ON attribute_definition (workspace_id, fill_from_name)');

        $this->addSql(<<<SQL
INSERT INTO attribute_policy (
id,
workspace_id,
name,
editable,
public,
created_at
) SELECT
gen_random_uuid(),
w.id,
'NamePolicy',
true,
true,
w.created_at
FROM workspace w
SQL);
        $this->addSql(<<<SQL
INSERT INTO attribute_definition (
id,
workspace_id,
policy_id,
name,
slug,
type,
searchable,
facet_enabled,
sortable,
translatable,
multiple,
allow_invalid,
editable,
position,
enabled,
created_at,
updated_at,
target,
name_priority,
fill_from_name,
suggest
) SELECT
gen_random_uuid(),
w.id,
ap.id,
'System Name',
'systemname',
'text',
true,
false,
false,
false,
false,
false,
true,
0,
true,
w.created_at,
w.created_at,
3,
10,
true,
true
FROM workspace w
INNER JOIN attribute_policy ap ON ap.workspace_id = w.id AND ap.name = 'NamePolicy'
SQL);

        $this->addSql(<<<SQL
UPDATE attribute_definition ad
SET name_priority = ana.priority + (ana.overrides::int * 11)
FROM asset_name_attribute ana
WHERE ana.workspace_id = ad.workspace_id AND ana.definition_id = ad.id
SQL);

        // Insert all assets' name to attribute table:
        $this->addSql(<<<SQL
INSERT INTO attribute (
id,
asset_id,
definition_id,
position,
value,
created_at,
updated_at,
locked,
origin,
confidence,
status
) SELECT
gen_random_uuid(),
a.id,
ad.id,
0,
a.name,
a.created_at,
a.created_at,
false,
1,
1.0,
0
FROM asset a
INNER JOIN attribute_definition ad ON ad.workspace_id = a.workspace_id AND ad.slug = 'systemname'
WHERE a.name IS NOT NULL AND a.name <> ''
SQL);

        // Rename Attribute definition from "System name" to "Name" (and slug from "system_name" to "name") but ignore if such an entry already exists:
        $this->addSql(<<<SQL
UPDATE attribute_definition ad
SET name = 'Name'
WHERE ad.name = 'System Name' AND NOT EXISTS (
    SELECT 1 FROM attribute_definition ad2 WHERE ad2.workspace_id = ad.workspace_id AND ad2.name = 'Name'
)
SQL);
        $this->addSql(<<<SQL
UPDATE attribute_definition ad
SET slug = 'name'
WHERE ad.slug = 'systemname' AND NOT EXISTS (
    SELECT 1 FROM attribute_definition ad2 WHERE ad2.workspace_id = ad.workspace_id AND ad2.slug = 'name'
)
SQL);

        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT fk_bcfcf5ae82d40a1f');
        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT fk_bcfcf5aed11ea911');
        $this->addSql('DROP TABLE asset_name_attribute');
        $this->addSql('ALTER TABLE asset DROP name');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration cannot be reversed because it drops the asset_name_attribute table and its data.');
    }
}
