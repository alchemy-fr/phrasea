<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves the form schemas and the target params onto the target: `target.form` holds one form of the
 * form builder, `target.data` the data merged into every commit.
 *
 * The schemas of a target (one per locale) are merged into one form. The base schema (a builder one,
 * else the one for every locale, else the most recent one) gives the texts for every language ("_"),
 * the other ones their translations. LiForm schemas are converted to the builder format without Databox:
 * every key stays a custom field (the data sent to Databox does not change) except the built-in ones;
 * what the builder cannot express is left out with a warning.
 *
 * Self-contained on purpose: it must keep working once the application code has changed.
 */
final class Version20261111120000 extends AbstractMigration
{
    private const string DEFAULT_LOCALE = '_';

    /**
     * Mirrors the "Builtin" enum of the form builder, `databox_tags` aside: tags need a Databox workspace.
     */
    private const array BUILTINS = [
        'databox_name' => ['type' => 'text', 'label' => ['en' => 'Name', 'fr' => 'Nom']],
        'databox_is_story' => ['type' => 'boolean', 'label' => ['en' => 'Group the files in a story', 'fr' => 'Regrouper les fichiers dans une story']],
        'databox_story_name' => ['type' => 'text', 'label' => ['en' => 'Story name', 'fr' => 'Nom de la story']],
        '__notify' => ['type' => 'boolean', 'label' => ['en' => 'Notify me when done', 'fr' => 'Me prévenir à la fin du traitement']],
    ];

    private const string TAGS_KEY = 'databox_tags';

    /**
     * Same as the form builder: an attribute slug, optionally suffixed by the locale of its value.
     */
    private const string KEY_PATTERN = '#^[A-Za-z0-9_.-]+(:[A-Za-z_-]{2,5})?$#';

    private const array WIDGETS = [
        'object', 'string', 'text', 'textarea', 'email', 'password', 'search', 'url', 'color', 'time',
        'number', 'integer', 'money', 'percent', 'boolean', 'checkbox', 'date', 'datetime', 'date-time',
        'compatible-date', 'compatible-datetime', 'choice', 'choice-expanded', 'choice-multiple-expanded',
        'array', 'oneOf',
    ];
    private const array DATE_TIME_WIDGETS = ['datetime', 'date-time', 'compatible-datetime', 'compatible-date-time'];
    private const array DATE_WIDGETS = ['date', 'compatible-date'];
    private const array NUMBER_WIDGETS = ['number', 'integer', 'money', 'percent'];

    public function getDescription(): string
    {
        return 'Form and data of the targets (form schemas and target params merged into target, LiForm converted)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE target ADD form JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE target ADD data JSON DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE target t SET data = tp.data
            FROM target_params tp
            WHERE tp.target_id = t.id AND tp.data::text NOT IN ('[]', '{}')
            SQL);

        // Read now: the queries above only run once up() returns, the tables still exist
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT fs.target_id, t.name AS target_name, fs.locale, fs.format, fs.data, fs.updated_at
            FROM form_schema fs
            INNER JOIN target t ON t.id = fs.target_id
            ORDER BY fs.target_id
            SQL);

        $byTarget = [];
        foreach ($rows as $row) {
            $byTarget[(string) $row['target_id']][] = $row;
        }

        foreach ($byTarget as $targetId => $targetRows) {
            $targetName = sprintf('"%s" (%s)', $targetRows[0]['target_name'], $targetId);
            $schemas = [];
            foreach ($targetRows as $row) {
                $data = json_decode((string) $row['data'], true);
                if (!is_array($data)) {
                    $this->warnIf(true, sprintf('Target %s: a form schema holding no JSON object is left out', $targetName));
                    continue;
                }
                $schemas[] = [
                    'locale' => '' === (string) $row['locale'] ? null : (string) $row['locale'],
                    'format' => (string) $row['format'],
                    'data' => $data,
                    'updatedAt' => (string) $row['updated_at'],
                ];
            }

            $result = self::mergeSchemas($schemas);
            foreach ($result['warnings'] as $warning) {
                $this->warnIf(true, sprintf('Target %s: %s', $targetName, $warning));
            }
            if (null === $result['form']) {
                continue;
            }

            $this->addSql('UPDATE target SET form = CAST(:form AS JSON) WHERE id = :id', [
                'form' => json_encode($result['form'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $targetId,
            ]);
        }

        // The ACEs on one schema or one params row pointed at deleted rows; the ones on every object stay
        $this->addSql("DELETE FROM access_control_entry WHERE object_type IN ('form_schema', 'target_params') AND object_id IS NOT NULL");

        $this->addSql('DROP TABLE form_schema');
        $this->addSql('DROP TABLE target_params');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The form schemas were merged and converted to the form builder format.');
    }

    /**
     * Merges the schemas of a target into one form of the builder.
     *
     * @param list<array{locale: ?string, format: string, data: array, updatedAt?: ?string}> $schemas
     *
     * @return array{form: ?array, warnings: list<string>}
     */
    public static function mergeSchemas(array $schemas): array
    {
        if ([] === $schemas) {
            return ['form' => null, 'warnings' => []];
        }

        usort($schemas, static fn (array $a, array $b): int => [
            'builder' === $a['format'] ? 0 : 1,
            null === $a['locale'] ? 0 : 1,
            $b['updatedAt'] ?? '',
        ] <=> [
            'builder' === $b['format'] ? 0 : 1,
            null === $b['locale'] ? 0 : 1,
            $a['updatedAt'] ?? '',
        ]);

        $base = array_shift($schemas);
        $warnings = [];
        if ('builder' === $base['format']) {
            $form = $base['data'];
        } else {
            try {
                ['definition' => $form, 'warnings' => $warnings] = self::convertLiForm($base['data']);
            } catch (\InvalidArgumentException $e) {
                return ['form' => null, 'warnings' => [sprintf('the LiForm schema cannot be converted (%s), the target has no form any more', $e->getMessage())]];
            }
        }

        foreach ($schemas as $translation) {
            try {
                if ('builder' === $translation['format']) {
                    self::mergeBuilderTranslations($form, $translation['data'], $translation['locale']);
                } else {
                    self::mergeLiFormTranslations($form, $translation['data'], $translation['locale'] ?? self::DEFAULT_LOCALE);
                }
            } catch (\InvalidArgumentException $e) {
                $warnings[] = sprintf('the translations of the "%s" schema are left out (%s)', $translation['locale'] ?? 'all locales', $e->getMessage());
            }
        }

        return ['form' => $form, 'warnings' => $warnings];
    }

    /**
     * Turns a LiForm schema into a form of the builder, its texts for every language ("_").
     *
     * @return array{definition: array, warnings: list<string>}
     *
     * @throws \InvalidArgumentException when a reference cannot be resolved
     */
    public static function convertLiForm(array $schema): array
    {
        $schema = self::compileSchema($schema, $schema);
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $locale = self::DEFAULT_LOCALE;
        $fields = [];
        $ids = [];
        $warnings = [];

        foreach (self::sortedProperties($schema) as [$key, $property]) {
            $key = (string) $key;
            if (self::TAGS_KEY === $key) {
                $warnings[] = sprintf('field "%s" left out: tags need a Databox workspace, add the field again in the form builder', $key);
                continue;
            }
            if (1 !== preg_match(self::KEY_PATTERN, $key)) {
                $warnings[] = sprintf('field "%s" left out: invalid key', $key);
                continue;
            }
            $kind = self::kindOf($property);
            if (null === $kind || ('boolean' === $kind['type'] && $kind['multiple'])) {
                $warnings[] = sprintf('field "%s" left out: not supported by the form builder (oneOf, object, array of objects…)', $key);
                continue;
            }

            $field = [
                'id' => self::newFieldId($ids),
                'source' => 'custom',
                'key' => $key,
                'type' => $kind['type'],
            ];

            if (isset(self::BUILTINS[$key])) {
                $field['source'] = 'builtin';
                $field['type'] = self::BUILTINS[$key]['type'];
            } else {
                if ($kind['multiple']) {
                    $field['multiple'] = true;
                }
                if (null !== $kind['widget']) {
                    $field['widget'] = $kind['widget'];
                }
                if ('choice' === $kind['type']) {
                    $field['choices'] = self::choicesOf($kind, $locale);
                    if ([] === $field['choices']) {
                        $warnings[] = sprintf('field "%s" left out: a choice field needs choices with a value', $key);
                        continue;
                    }
                }
            }

            $field['label'] = self::localizedText($property['title'] ?? null, $locale);
            if ([] === $field['label'] && isset(self::BUILTINS[$key])) {
                $field['label'] = self::BUILTINS[$key]['label'];
            }
            if ([] !== $help = self::localizedText($property['description'] ?? null, $locale)) {
                $field['help'] = $help;
            }
            if ([] !== $placeholder = self::localizedText($property['placeholder'] ?? null, $locale)) {
                $field['placeholder'] = $placeholder;
            }
            if (in_array($key, $required, true)) {
                $field['required'] = true;
            }
            $defaultValue = self::defaultValueOf($property);
            if (null !== $defaultValue) {
                $field['defaultValue'] = $defaultValue;
            }
            if ([] !== $constraints = self::constraintsOf($property)) {
                $field['constraints'] = $constraints;
            }

            $fields[] = $field;
        }

        return [
            'definition' => [
                'version' => 1,
                'workspaceId' => null,
                'fields' => $fields,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Adds the texts of a LiForm schema of another locale to the matching fields (by key, choices by value),
     * without replacing a text already there.
     */
    private static function mergeLiFormTranslations(array &$definition, array $schema, string $locale): void
    {
        $schema = self::compileSchema($schema, $schema);
        foreach (self::sortedProperties($schema) as [$key, $property]) {
            $index = self::findField($definition, (string) $key);
            if (null === $index) {
                continue;
            }
            $field = &$definition['fields'][$index];

            self::addText($field, 'label', $locale, $property['title'] ?? null);
            self::addText($field, 'help', $locale, $property['description'] ?? null);
            self::addText($field, 'placeholder', $locale, $property['placeholder'] ?? null);

            $kind = self::kindOf($property);
            if (isset($field['choices']) && is_array($field['choices']) && null !== $kind) {
                foreach ($kind['enumValues'] as $i => $value) {
                    $title = $kind['enumTitles'][$i] ?? null;
                    foreach ($field['choices'] as &$choice) {
                        if (is_array($choice) && ($choice['value'] ?? null) === self::jsString($value)) {
                            self::addText($choice, 'label', $locale, $title);
                        }
                    }
                    unset($choice);
                }
            }
            unset($field);
        }
    }

    /**
     * Adds the texts of a builder form of another locale to the matching fields: its texts for every
     * language are the ones of its locale.
     */
    private static function mergeBuilderTranslations(array &$definition, array $other, ?string $locale): void
    {
        $mapLocale = static fn (string $l): string => self::DEFAULT_LOCALE === $l && null !== $locale ? $locale : $l;

        foreach (is_array($other['fields'] ?? null) ? $other['fields'] : [] as $otherField) {
            if (!is_array($otherField) || 'layout' === ($otherField['source'] ?? null) || !is_string($otherField['key'] ?? null)) {
                continue;
            }
            $index = self::findField($definition, $otherField['key']);
            if (null === $index) {
                continue;
            }
            $field = &$definition['fields'][$index];

            foreach (['label', 'help', 'placeholder'] as $property) {
                foreach (is_array($otherField[$property] ?? null) ? $otherField[$property] : [] as $l => $text) {
                    self::addText($field, $property, $mapLocale((string) $l), $text);
                }
            }

            if (isset($field['choices']) && is_array($field['choices'])) {
                foreach (is_array($otherField['choices'] ?? null) ? $otherField['choices'] : [] as $otherChoice) {
                    foreach ($field['choices'] as &$choice) {
                        if (is_array($choice) && is_array($otherChoice) && ($choice['value'] ?? null) === ($otherChoice['value'] ?? false)) {
                            foreach (is_array($otherChoice['label'] ?? null) ? $otherChoice['label'] : [] as $l => $text) {
                                self::addText($choice, 'label', $mapLocale((string) $l), $text);
                            }
                        }
                    }
                    unset($choice);
                }
            }
            unset($field);
        }
    }

    private static function findField(array $definition, string $key): ?int
    {
        foreach ($definition['fields'] ?? [] as $i => $field) {
            if (is_array($field) && 'layout' !== ($field['source'] ?? null) && ($field['key'] ?? null) === $key) {
                return $i;
            }
        }

        return null;
    }

    private static function addText(array &$item, string $property, string $locale, mixed $text): void
    {
        if (!is_string($text) || '' === $text) {
            return;
        }
        $map = is_array($item[$property] ?? null) ? $item[$property] : [];
        if (!isset($map[$locale]) || '' === $map[$locale]) {
            $map[$locale] = $text;
            $item[$property] = $map;
        }
    }

    /**
     * What a LiForm property is, in builder terms; null when it has no equivalent.
     *
     * @return array{type: string, multiple: bool, widget: ?string, enumValues: list<mixed>, enumTitles: list<mixed>}|null
     */
    private static function kindOf(array $field): ?array
    {
        $widget = self::guessWidget($field);
        $kind = static fn (string $type, ?string $widget = null, ?array $enum = null, mixed $titles = null, bool $multiple = false): array => [
            'type' => $type,
            'multiple' => $multiple,
            'widget' => $widget,
            'enumValues' => array_values($enum ?? []),
            'enumTitles' => is_array($titles) ? array_values($titles) : [],
        ];

        if ('array' === ($field['type'] ?? null)) {
            $items = is_array($field['items'] ?? null) ? $field['items'] : [];
            if (self::isList($items['enum'] ?? null)) {
                return $kind('choice', 'choice-multiple-expanded' === $widget ? 'checkboxes' : 'autocomplete', $items['enum'], $items['enum_titles'] ?? null, true);
            }
            $item = isset($items['type']) ? self::kindOf($items) : null;
            if (null === $item || $item['multiple']) {
                return null;
            }
            $item['multiple'] = true;

            return $item;
        }

        if (self::isList($field['enum'] ?? null)) {
            return $kind('choice', 'choice-expanded' === $widget ? 'radios' : 'select', $field['enum'], $field['enum_titles'] ?? null);
        }
        if (isset($field['oneOf']) || 'object' === ($field['type'] ?? null)) {
            return null;
        }
        if ('boolean' === ($field['type'] ?? null) || 'checkbox' === $widget) {
            return $kind('boolean');
        }
        $type = $field['type'] ?? null;
        if ('number' === $type || 'integer' === $type || in_array($widget, self::NUMBER_WIDGETS, true)) {
            return $kind('number');
        }
        $format = $field['format'] ?? null;
        if (in_array($widget, self::DATE_TIME_WIDGETS, true) || 'date-time' === $format) {
            return $kind('date_time');
        }
        if (in_array($widget, self::DATE_WIDGETS, true) || 'date' === $format) {
            return $kind('date');
        }
        if ('color' === $widget) {
            return $kind('color');
        }
        if ('textarea' === $widget) {
            return $kind('textarea');
        }

        return $kind('text');
    }

    /**
     * The widget of a field, in this order: the explicit `widget`, `choice` for an enum, `oneOf`,
     * the `format` when it names a widget, then the `type`.
     */
    private static function guessWidget(array $field): string
    {
        if (is_string($field['widget'] ?? null) && '' !== $field['widget']) {
            return $field['widget'];
        }
        if (self::isList($field['enum'] ?? null)) {
            return 'choice';
        }
        if (self::isList($field['oneOf'] ?? null)) {
            return 'oneOf';
        }
        if (is_string($field['format'] ?? null) && in_array($field['format'], self::WIDGETS, true)) {
            return $field['format'];
        }
        if ('array' === ($field['type'] ?? null) && isset($field['items']['enum']) && !empty($field['uniqueItems'])) {
            return 'choice-multiple';
        }

        return is_string($field['type'] ?? null) ? $field['type'] : 'object';
    }

    /**
     * Resolves the local `$ref` (against the root of the schema) and merges the `allOf` of every node.
     *
     * @throws \InvalidArgumentException
     */
    private static function compileSchema(array $schema, array $root, int $depth = 0): array
    {
        if ($depth > 32) {
            throw new \InvalidArgumentException('Schema too deep (circular $ref?)');
        }

        if (is_string($schema['$ref'] ?? null)) {
            $target = self::resolveRef($schema['$ref'], $root);
            $rest = $schema;
            unset($rest['$ref']);

            // The target may itself be a reference
            return self::compileSchema(self::mergeDeep(self::isObject($target) ? $target : [], $rest), $root, $depth + 1);
        }

        $result = [];
        foreach ($schema as $key => $value) {
            if ('allOf' !== $key) {
                $result[$key] = self::compileNode($value, $root, $depth);
            }
        }
        if (self::isList($schema['allOf'] ?? null)) {
            foreach ($schema['allOf'] as $part) {
                $part = self::compileNode($part, $root, $depth);
                if (self::isObject($part)) {
                    $result = self::mergeDeep($result, $part);
                }
            }
        }

        return $result;
    }

    private static function compileNode(mixed $node, array $root, int $depth): mixed
    {
        if (!is_array($node) || [] === $node) {
            return $node;
        }
        if (array_is_list($node)) {
            return array_map(static fn (mixed $n): mixed => self::compileNode($n, $root, $depth), $node);
        }

        return self::compileSchema($node, $root, $depth + 1);
    }

    private static function resolveRef(string $uri, array $root): mixed
    {
        if (!str_starts_with($uri, '#')) {
            throw new \InvalidArgumentException(sprintf('Unsupported $ref "%s": only local references', $uri));
        }

        $node = $root;
        foreach (explode('/', (string) preg_replace('#^\#/?#', '', $uri)) as $token) {
            if ('' === $token) {
                continue;
            }
            $token = str_replace(['~1', '~0'], ['/', '~'], $token);
            if (!is_array($node)) {
                throw new \InvalidArgumentException(sprintf('Unresolvable $ref "%s"', $uri));
            }
            $node = $node[$token] ?? null;
        }

        return $node;
    }

    private static function mergeDeep(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            $current = $target[$key] ?? null;
            $target[$key] = self::isObject($current) && self::isObject($value)
                ? self::mergeDeep($current, $value)
                : $value;
        }

        return $target;
    }

    /**
     * The properties of an object node, in `propertyOrder` order (stable otherwise).
     *
     * @return list<array{0: string|int, 1: array}>
     */
    private static function sortedProperties(array $schema): array
    {
        $properties = [];
        $index = 0;
        foreach (self::isObject($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            if (is_array($property)) {
                $properties[] = [$name, $property, $index++];
            }
        }
        usort($properties, static fn (array $a, array $b): int => [(float) ($a[1]['propertyOrder'] ?? 0), $a[2]] <=> [(float) ($b[1]['propertyOrder'] ?? 0), $b[2]]);

        return array_map(static fn (array $p): array => [$p[0], $p[1]], $properties);
    }

    /**
     * @return list<array{value: string, label: array<string, string>}>
     */
    private static function choicesOf(array $kind, string $locale): array
    {
        $choices = [];
        foreach ($kind['enumValues'] as $i => $value) {
            $value = self::jsString($value);
            if ('' === $value || isset($choices[$value])) {
                continue;
            }
            $title = $kind['enumTitles'][$i] ?? null;
            $choices[$value] = [
                'value' => $value,
                'label' => self::localizedText(is_scalar($title) ? (string) $title : $value, $locale),
            ];
        }

        return array_values($choices);
    }

    private static function defaultValueOf(array $field): mixed
    {
        $value = $field['defaultValue'] ?? $field['default'] ?? null;
        if (null === $value || is_scalar($value)) {
            return $value;
        }
        if (is_array($value) && array_is_list($value)) {
            return array_map(self::jsString(...), $value);
        }

        return null;
    }

    /**
     * @return array<string, int|float>
     */
    private static function constraintsOf(array $field): array
    {
        $constraints = [];
        foreach (['minLength' => 'minLength', 'maxLength' => 'maxLength'] as $from => $to) {
            if (is_int($field[$from] ?? null) && $field[$from] >= 0) {
                $constraints[$to] = $field[$from];
            }
        }
        foreach (['minimum' => 'min', 'maximum' => 'max'] as $from => $to) {
            if (is_int($field[$from] ?? null) || is_float($field[$from] ?? null)) {
                $constraints[$to] = $field[$from];
            }
        }

        return $constraints;
    }

    /**
     * @return array<string, string>
     */
    private static function localizedText(mixed $text, string $locale): array
    {
        return is_string($text) && '' !== $text ? [$locale => $text] : [];
    }

    /**
     * A value as the form builder stores it: the way JavaScript turns it into a string.
     */
    private static function jsString(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    /**
     * @param array<string, true> $ids the ids already given
     */
    private static function newFieldId(array &$ids): string
    {
        do {
            $id = 'f_';
            for ($i = 0; $i < 8; ++$i) {
                $id .= base_convert((string) random_int(0, 35), 10, 36);
            }
        } while (isset($ids[$id]));
        $ids[$id] = true;

        return $id;
    }

    private static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ([] === $value || !array_is_list($value));
    }
}
