<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Databox has no tags any more: its migration Version20261116203000 turned the tags of each workspace into
 * the entities of a list "Tags" (keeping the tag UUIDs) and the tags of the assets into the values of a
 * multi-valued entity attribute of slug "tags". The built-in field `databox_tags` of the forms becomes a field
 * of that attribute; the key `databox_tags` of the target data and of the commits not yet acknowledged by
 * Databox becomes the key of that field.
 *
 * Self-contained on purpose: it must keep working once the application code has changed.
 */
final class Version20261116213000 extends AbstractMigration
{
    private const string TAGS_KEY = 'databox_tags';

    /**
     * The slug of the migrated attribute, then the one Databox falls back to when a workspace already
     * had an attribute "tags".
     */
    private const array ATTRIBUTE_SLUGS = ['tags', 'tagsmigrated'];

    /**
     * Suffixes of the deterministic IDs Databox gave the list and the attribute definition of a workspace:
     * md5(workspace ID || suffix) as a UUID (see Version20261116203000 of Databox).
     */
    private const string LIST_SUFFIX = 'tags-list';
    private const string DEFINITION_SUFFIX = 'tags-definition';

    public function getDescription(): string
    {
        return 'Convert the built-in "databox_tags" field of the forms into a field of the Databox attribute "tags"';
    }

    public function up(Schema $schema): void
    {
        $keys = [];
        $targets = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id, name, form, data FROM target
            WHERE form::text LIKE '%"databox_tags"%' OR data::text LIKE '%"databox_tags"%'
            SQL);

        foreach ($targets as $target) {
            $targetId = (string) $target['id'];
            $targetName = sprintf('"%s" (%s)', $target['name'], $targetId);

            $form = null !== $target['form'] ? json_decode((string) $target['form'], true) : null;
            if (is_array($form)) {
                $result = self::convertForm($form);
                foreach ($result['warnings'] as $warning) {
                    $this->warnIf(true, sprintf('Target %s: %s', $targetName, $warning));
                }
                $form = $result['form'];
                $keys[$targetId] = $result['key'];
            }

            $data = null !== $target['data'] ? json_decode((string) $target['data'], true) : null;
            if (is_array($data)) {
                // An empty object is null, as when saved through the API
                $data = self::convertData($data, $keys[$targetId] ?? self::ATTRIBUTE_SLUGS[0]) ?: null;
            }

            $this->addSql('UPDATE target SET form = CAST(:form AS JSON), data = CAST(:data AS JSON) WHERE id = :id', [
                'form' => self::encode($form),
                'data' => self::encode($data),
                'id' => $targetId,
            ]);
        }

        $commits = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id, target_id, form_data FROM asset_commit
            WHERE acknowledged = false AND form_data::text LIKE '%"databox_tags"%'
            SQL);

        foreach ($commits as $commit) {
            $formData = json_decode((string) $commit['form_data'], true);
            if (!is_array($formData)) {
                continue;
            }

            $this->addSql('UPDATE asset_commit SET form_data = CAST(:formData AS JSON) WHERE id = :id', [
                'formData' => self::encode(self::convertData($formData, $keys[(string) $commit['target_id']] ?? self::ATTRIBUTE_SLUGS[0])),
                'id' => (string) $commit['id'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Databox does not convert the "tags" attribute back to tags.');
    }

    /**
     * Replaces the built-in field `databox_tags` of a form by a field of the "tags" entity attribute of its
     * workspace. Its settings (labels, default value, widget…) are kept: the tag UUIDs are the entity UUIDs.
     *
     * @return array{form: array, key: ?string, warnings: list<string>} the key the tags are sent as now, null when the form has no tags field
     */
    public static function convertForm(array $form): array
    {
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $index = null;
        $usedKeys = [];
        foreach ($fields as $i => $field) {
            if (!is_array($field) || !isset($field['key']) || !is_string($field['key'])) {
                continue;
            }
            if (null === $index && 'builtin' === ($field['source'] ?? null) && self::TAGS_KEY === $field['key']) {
                $index = $i;
            } else {
                $usedKeys[] = $field['key'];
            }
        }

        if (null === $index) {
            return ['form' => $form, 'key' => null, 'warnings' => []];
        }

        $workspaceId = $form['workspaceId'] ?? null;
        if (!is_string($workspaceId) || '' === $workspaceId) {
            unset($fields[$index]);
            $form['fields'] = array_values($fields);

            return ['form' => $form, 'key' => null, 'warnings' => [sprintf('field "%s" left out: the form has no workspace', self::TAGS_KEY)]];
        }

        $warnings = [];
        $key = null;
        foreach (self::ATTRIBUTE_SLUGS as $slug) {
            if (!in_array($slug, $usedKeys, true)) {
                $key = $slug;
                break;
            }
        }
        if (null === $key) {
            unset($fields[$index]);
            $form['fields'] = array_values($fields);

            return ['form' => $form, 'key' => null, 'warnings' => [sprintf('field "%s" left out: the form already has the keys "%s"', self::TAGS_KEY, implode('", "', self::ATTRIBUTE_SLUGS))]];
        }
        if (self::ATTRIBUTE_SLUGS[0] !== $key) {
            $warnings[] = sprintf('field "%s" converted to the key "%s" as the form already has a field "%s": check that it is the slug of the migrated attribute in Databox', self::TAGS_KEY, $key, self::ATTRIBUTE_SLUGS[0]);
        }

        $workspaceId = strtolower($workspaceId);
        $old = $fields[$index];
        unset($old['id'], $old['source'], $old['key'], $old['type'], $old['multiple'], $old['attribute'], $old['choices']);

        $fields[$index] = [
            'id' => $fields[$index]['id'] ?? null,
            'source' => 'attribute',
            'key' => $key,
            'type' => 'entity',
            'multiple' => true,
            ...$old,
            'attribute' => [
                'id' => self::deterministicUuid($workspaceId, self::DEFINITION_SUFFIX),
                'slug' => $key,
                'databoxType' => 'entity',
                'translatable' => false,
                'entityListId' => self::deterministicUuid($workspaceId, self::LIST_SUFFIX),
                'entityListName' => 'Tags',
            ],
        ];
        $form['fields'] = $fields;

        return ['form' => $form, 'key' => $key, 'warnings' => $warnings];
    }

    /**
     * Moves the values of the key `databox_tags` to the given key, merged with the values already there.
     */
    public static function convertData(array $data, string $key): array
    {
        if (!array_key_exists(self::TAGS_KEY, $data)) {
            return $data;
        }

        $tags = $data[self::TAGS_KEY];
        unset($data[self::TAGS_KEY]);
        if (null === $tags || [] === $tags || '' === $tags) {
            return $data;
        }

        if (isset($data[$key])) {
            $tags = array_values(array_unique(array_merge(
                is_array($data[$key]) ? array_values($data[$key]) : [$data[$key]],
                is_array($tags) ? array_values($tags) : [$tags],
            ), SORT_REGULAR));
        }
        $data[$key] = $tags;

        return $data;
    }

    /**
     * Same as `md5(workspace_id::text || suffix)::uuid` in PostgreSQL.
     */
    public static function deterministicUuid(string $workspaceId, string $suffix): string
    {
        $hash = md5($workspaceId.$suffix);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20));
    }

    private static function encode(?array $value): ?string
    {
        return null !== $value ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }
}
