<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116203000 extends AbstractMigration
{
    /**
     * Suffixes of the deterministic IDs (md5(workspace ID || suffix)) of what is created per workspace.
     */
    private const string POLICY_SUFFIX = 'tags-policy';
    private const string LIST_SUFFIX = 'tags-list';
    private const string DEFINITION_SUFFIX = 'tags-definition';

    /**
     * Matches the "@tag" built-in field in an AQL text ("\M": end of word, so not "@tags").
     */
    private const string AQL_TAG_PATTERN = '@tag\M';

    public function getDescription(): string
    {
        return 'Replace the tags with an entity list "Tags" and a multi-valued entity attribute "Tags" per workspace';
    }

    public function up(Schema $schema): void
    {
        // Read before the queued statements run, while the tag table still exists
        $this->convertAutomations();

        // A workspace gets the list and the attribute when it has tags, or a filter rule on "@tag":
        // the rule must keep resolving its field, or it would hide the whole workspace from its targets.
        $this->addSql(sprintf(<<<'SQL'
            CREATE TEMPORARY TABLE migrated_tags_workspace AS
            SELECT w.id AS workspace_id,
                md5(w.id::text || '%1$s')::uuid AS policy_id,
                md5(w.id::text || '%2$s')::uuid AS list_id,
                md5(w.id::text || '%3$s')::uuid AS definition_id,
                CASE WHEN EXISTS (SELECT 1 FROM attribute_policy p WHERE p.workspace_id = w.id AND p.name = 'Tags')
                    THEN 'Tags (migrated)' ELSE 'Tags' END AS policy_name,
                CASE WHEN EXISTS (SELECT 1 FROM entity_list l WHERE l.workspace_id = w.id AND l.name = 'Tags')
                    THEN 'Tags (migrated)' ELSE 'Tags' END AS list_name,
                CASE WHEN EXISTS (SELECT 1 FROM attribute_definition d WHERE d.workspace_id = w.id AND (d.name = 'Tags' OR d.slug = 'tags'))
                    THEN 'Tags (migrated)' ELSE 'Tags' END AS definition_name,
                CASE WHEN EXISTS (SELECT 1 FROM attribute_definition d WHERE d.workspace_id = w.id AND (d.name = 'Tags' OR d.slug = 'tags'))
                    THEN 'tagsmigrated' ELSE 'tags' END AS definition_slug
            FROM workspace w
            WHERE EXISTS (SELECT 1 FROM tag t WHERE t.workspace_id = w.id)
               OR EXISTS (SELECT 1 FROM attribute_filter_rule r WHERE r.workspace_id = w.id AND r.condition ~* '%4$s')
            SQL, self::POLICY_SUFFIX, self::LIST_SUFFIX, self::DEFINITION_SUFFIX, self::AQL_TAG_PATTERN));

        // Readable by whoever reads the asset, editable with the EDIT_ATTRIBUTES permission, like the tags
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_policy (id, workspace_id, name, editable, public, key, labels, created_at)
            SELECT m.policy_id, m.workspace_id, m.policy_name, true, true, NULL, NULL, NOW()
            FROM migrated_tags_workspace m
            SQL);

        // A list without ACE is read by the readers of the workspace (EntityListVoter)
        $this->addSql(<<<'SQL'
            INSERT INTO entity_list (id, workspace_id, owner_id, name, options, created_at, updated_at)
            SELECT m.list_id, m.workspace_id, NULL, m.list_name, '{"wc":true,"wt":true}'::json, NOW(), NOW()
            FROM migrated_tags_workspace m
            SQL);

        // The entities keep the IDs of the tags: the AQL conditions on them stay valid
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_entity (id, workspace_id, list_id, value, position, status, data, translations, created_at, updated_at)
            SELECT t.id, t.workspace_id, m.list_id, t.name,
                (row_number() OVER (PARTITION BY t.workspace_id ORDER BY t.name, t.id)) - 1,
                0,
                CASE WHEN t.color IS NOT NULL AND t.color <> '' THEN json_build_object('c', '#' || t.color) END,
                (
                    SELECT jsonb_object_agg(tr.locale, tr.value)::json
                    FROM (
                        SELECT CASE WHEN strpos(k.locale, '_') > 0
                                THEN lower(split_part(k.locale, '_', 1)) || '_' || upper(substr(k.locale, strpos(k.locale, '_') + 1))
                                ELSE lower(k.locale)
                            END AS locale,
                            k.value
                        FROM (
                            SELECT replace(trim(e.key), '-', '_') AS locale, e.value
                            FROM json_each_text(CASE WHEN json_typeof(t.translations -> 'name') = 'object' THEN t.translations -> 'name' ELSE '{}'::json END) e
                            WHERE e.value IS NOT NULL AND trim(e.value) <> ''
                        ) k
                    ) tr
                ),
                t.created_at, t.updated_at
            FROM tag t
            INNER JOIN migrated_tags_workspace m ON m.workspace_id = t.workspace_id
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO attribute_definition (
                id, workspace_id, policy_id, entity_list_id, name, slug, type,
                searchable, facet_enabled, sortable, translatable, multiple, allow_invalid, suggest, editable,
                fill_from_name, enabled, target, position, created_at, updated_at
            )
            SELECT m.definition_id, m.workspace_id, m.policy_id, m.list_id, m.definition_name, m.definition_slug, 'entity',
                true, true, false, false, true, false, false, true,
                false, true, 3,
                COALESCE((SELECT MAX(d.position) FROM attribute_definition d WHERE d.workspace_id = m.workspace_id), -1) + 1,
                NOW(), NOW()
            FROM migrated_tags_workspace m
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO attribute (id, asset_id, definition_id, value, locale, position, invalid, locked, origin, status, confidence, created_at, updated_at)
            SELECT gen_random_uuid(), at.asset_id, m.definition_id, at.tag_id::text, NULL,
                (row_number() OVER (PARTITION BY at.asset_id ORDER BY t.name, t.id)) - 1,
                false, false, 1, 0, 1.0, a.tags_edited_at, a.tags_edited_at
            FROM asset_tag at
            INNER JOIN tag t ON t.id = at.tag_id
            INNER JOIN asset a ON a.id = at.asset_id AND a.workspace_id = t.workspace_id
            INNER JOIN migrated_tags_workspace m ON m.workspace_id = t.workspace_id
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO template_attribute (id, template_id, definition_id, value, locale, position, invalid, created_at, updated_at)
            SELECT gen_random_uuid(), tt.asset_data_template_id, m.definition_id, tt.tag_id::text, NULL,
                (row_number() OVER (PARTITION BY tt.asset_data_template_id ORDER BY t.name, t.id)) - 1,
                false, NOW(), NOW()
            FROM asset_data_template_tag tt
            INNER JOIN tag t ON t.id = tt.tag_id
            INNER JOIN asset_data_template adt ON adt.id = tt.asset_data_template_id AND adt.workspace_id = t.workspace_id
            INNER JOIN migrated_tags_workspace m ON m.workspace_id = t.workspace_id
            SQL);

        // Visibility rules: the slug of the attribute of their workspace
        $this->addSql(sprintf(<<<'SQL'
            UPDATE attribute_filter_rule r
            SET condition = regexp_replace(r.condition, '%1$s', m.definition_slug, 'gi')
            FROM migrated_tags_workspace m
            WHERE m.workspace_id = r.workspace_id AND r.condition ~* '%1$s'
            SQL, self::AQL_TAG_PATTERN));

        // A saved search is not bound to a workspace: the usual slug
        $this->addSql(sprintf(<<<'SQL'
            UPDATE saved_search
            SET data = regexp_replace(data::text, '%1$s', 'tags', 'gi')::json
            WHERE data::text ~* '%1$s'
            SQL, self::AQL_TAG_PATTERN));

        // A profile is not bound to a workspace and resolves its items by definition ID:
        // the "@tag" built-in becomes one item per migrated definition
        $this->addSql(sprintf(<<<'SQL'
            INSERT INTO profile_item (id, profile_id, section, type, key, definition_id, options, position)
            SELECT md5(pi.id::text || m.definition_id::text)::uuid, pi.profile_id, pi.section, 0, NULL, m.definition_id,
                CASE WHEN pi.options IS NOT NULL THEN regexp_replace(pi.options::text, '%1$s', m.definition_slug, 'gi')::json END,
                pi.position
            FROM profile_item pi
            CROSS JOIN migrated_tags_workspace m
            WHERE pi.type = 1 AND pi.key = '@tag'
            SQL, self::AQL_TAG_PATTERN));
        $this->addSql('DELETE FROM profile_item WHERE type = 1 AND key = \'@tag\'');
        // Default queries of the pinned filters
        $this->addSql(sprintf(<<<'SQL'
            UPDATE profile_item
            SET options = regexp_replace(options::text, '%1$s', 'tags', 'gi')::json
            WHERE options IS NOT NULL AND options::text ~* '%1$s'
            SQL, self::AQL_TAG_PATTERN));

        $this->addSql(<<<'SQL'
            UPDATE webhook
            SET events = (
                SELECT COALESCE(json_agg(e.value), '[]'::json)
                FROM json_array_elements_text(webhook.events) e
                WHERE e.value NOT LIKE 'tag:%'
            )
            WHERE events IS NOT NULL AND json_typeof(events) = 'array' AND events::text LIKE '%"tag:%'
            SQL);

        $this->addSql('DROP TABLE migrated_tags_workspace');

        $this->addSql('DROP TABLE asset_data_template_tag');
        $this->addSql('DROP TABLE asset_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('ALTER TABLE asset DROP tags_edited_at');
    }

    public function down(Schema $schema): void
    {
        // Schema only: the values, the AQL conditions, the profiles and the automations are not converted back
        $this->addSql('CREATE TABLE tag (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(6) DEFAULT NULL, translations JSON DEFAULT NULL, locale VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_389B78382D40A1F ON tag (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX ws_name_uniq ON tag (workspace_id, name)');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B78382D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE asset_tag (asset_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(asset_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_6983740F5DA1941 ON asset_tag (asset_id)');
        $this->addSql('CREATE INDEX IDX_6983740FBAD26311 ON asset_tag (tag_id)');
        $this->addSql('ALTER TABLE asset_tag ADD CONSTRAINT FK_6983740F5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_tag ADD CONSTRAINT FK_6983740FBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE asset_data_template_tag (asset_data_template_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(asset_data_template_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_6AF3D610C39A0AC3 ON asset_data_template_tag (asset_data_template_id)');
        $this->addSql('CREATE INDEX IDX_6AF3D610BAD26311 ON asset_data_template_tag (tag_id)');
        $this->addSql('ALTER TABLE asset_data_template_tag ADD CONSTRAINT FK_6AF3D610C39A0AC3 FOREIGN KEY (asset_data_template_id) REFERENCES asset_data_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_data_template_tag ADD CONSTRAINT FK_6AF3D610BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE asset ADD tags_edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE asset SET tags_edited_at = updated_at');
        $this->addSql('ALTER TABLE asset ALTER tags_edited_at SET NOT NULL');

        $definitions = sprintf("SELECT md5(w.id::text || '%s')::uuid FROM workspace w", self::DEFINITION_SUFFIX);
        $lists = sprintf("SELECT md5(w.id::text || '%s')::uuid FROM workspace w", self::LIST_SUFFIX);
        $policies = sprintf("SELECT md5(w.id::text || '%s')::uuid FROM workspace w", self::POLICY_SUFFIX);
        $this->addSql(sprintf('DELETE FROM profile_item WHERE definition_id IN (%s)', $definitions));
        $this->addSql(sprintf('DELETE FROM template_attribute WHERE definition_id IN (%s)', $definitions));
        $this->addSql(sprintf('DELETE FROM attribute WHERE definition_id IN (%s)', $definitions));
        $this->addSql(sprintf('DELETE FROM attribute_definition WHERE id IN (%s)', $definitions));
        $this->addSql(sprintf('DELETE FROM attribute_entity WHERE list_id IN (%s)', $lists));
        $this->addSql(sprintf('DELETE FROM entity_list WHERE id IN (%s)', $lists));
        $this->addSql(sprintf('DELETE FROM attribute_policy WHERE id IN (%s)', $policies));
    }

    /**
     * Each "tags" action becomes "attribute" actions on the "Tags" definition of the workspaces of its tags.
     */
    private function convertAutomations(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, actions FROM saved_search_automation WHERE actions::text LIKE \'%"tags"%\'');
        if (empty($rows)) {
            return;
        }

        $tagWorkspaces = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, workspace_id FROM tag') as $tag) {
            $tagWorkspaces[strtolower((string) $tag['id'])] = (string) $tag['workspace_id'];
        }

        foreach ($rows as $row) {
            $actions = json_decode((string) $row['actions'], true);
            if (!is_array($actions)) {
                continue;
            }
            $converted = self::convertAutomationActions($actions, $tagWorkspaces);
            if (null === $converted) {
                continue;
            }

            $this->addSql(
                empty($converted)
                    ? 'UPDATE saved_search_automation SET actions = :actions, enabled = false WHERE id = :id'
                    : 'UPDATE saved_search_automation SET actions = :actions WHERE id = :id',
                [
                    'actions' => json_encode($converted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'id' => (string) $row['id'],
                ],
            );
        }
    }

    /**
     * Replaces each "tags" action ({type: "tags", config: {add: [tag IDs], remove: [tag IDs]}}) by, for each
     * workspace of its tags (in the order they come), one "attribute" action "add" per added tag and an
     * "attribute" action "delete" of the removed tags. Unknown tags are left out.
     *
     * @param list<array{type?: mixed, config?: mixed}> $actions
     * @param array<string, string>                     $tagWorkspaces workspace ID by (lowercase) tag ID
     *
     * @return list<array{type: mixed, config: mixed}>|null the new actions, null when there is no "tags" action
     */
    public static function convertAutomationActions(array $actions, array $tagWorkspaces): ?array
    {
        $changed = false;
        $result = [];
        foreach ($actions as $action) {
            if (!is_array($action) || 'tags' !== ($action['type'] ?? null)) {
                $result[] = $action;
                continue;
            }
            $changed = true;

            $config = is_array($action['config'] ?? null) ? $action['config'] : [];
            $byWorkspace = [];
            foreach (['add', 'remove'] as $key) {
                foreach (is_array($config[$key] ?? null) ? $config[$key] : [] as $tagId) {
                    if (!is_string($tagId)) {
                        continue;
                    }
                    $tagId = strtolower($tagId);
                    if (null === $workspaceId = $tagWorkspaces[$tagId] ?? null) {
                        continue;
                    }
                    $byWorkspace[$workspaceId][$key][$tagId] = true;
                }
            }

            foreach ($byWorkspace as $workspaceId => $tags) {
                $definitionId = self::getDefinitionId((string) $workspaceId);
                foreach (array_keys($tags['add'] ?? []) as $tagId) {
                    $result[] = [
                        'type' => 'attribute',
                        'config' => [
                            'definitionId' => $definitionId,
                            'action' => 'add',
                            'locale' => null,
                            'value' => (string) $tagId,
                        ],
                    ];
                }
                if (!empty($tags['remove'])) {
                    $result[] = [
                        'type' => 'attribute',
                        'config' => [
                            'definitionId' => $definitionId,
                            'action' => 'delete',
                            'locale' => null,
                            'value' => array_map('strval', array_keys($tags['remove'])),
                        ],
                    ];
                }
            }
        }

        return $changed ? $result : null;
    }

    /**
     * The ID of the "Tags" definition of the workspace, as PostgreSQL computes it (md5(…)::uuid).
     */
    public static function getDefinitionId(string $workspaceId): string
    {
        $hash = md5(strtolower($workspaceId).self::DEFINITION_SUFFIX);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20));
    }
}
