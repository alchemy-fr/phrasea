<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126203500 extends AbstractMigration
{
    private const array DISPLAY_KEYS = ['display', 'displayBatchEdit'];
    private const array RATIO_KEYS = ['sizeRatio', 'attributesRatio'];
    private const array LAYOUTS = [
        'g' => 'grid',
        'l' => 'list',
    ];

    /**
     * Range of the preview ratio sliders.
     */
    private const float MIN_RATIO = 0.2;
    private const float MAX_RATIO = 0.8;

    public function getDescription(): string
    {
        return 'Convert the display preferences saved by the legacy client (preview ratios in percent, layout g/l) in the profiles and the user preferences';
    }

    public function up(Schema $schema): void
    {
        $this->convertTable('profile_data');
        $this->convertTable('user_preference');
    }

    public function down(Schema $schema): void
    {
        // The values are not converted back: the current client cannot read the legacy ones
    }

    private function convertTable(string $table): void
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT id, data FROM %s WHERE data IS NOT NULL AND (data::text LIKE \'%%Ratio"%%\' OR data::text LIKE \'%%"layout"%%\')',
            $table,
        ));

        foreach ($rows as $row) {
            $data = json_decode((string) $row['data'], true);
            if (!is_array($data)) {
                continue;
            }
            $converted = self::convertPreferences($data);
            if (null === $converted) {
                continue;
            }

            $this->addSql(sprintf('UPDATE %s SET data = :data WHERE id = :id', $table), [
                'data' => json_encode($converted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                'id' => (string) $row['id'],
            ]);
        }
    }

    /**
     * The legacy client stored the preview ratios in percent (60 for 60% of the viewport) and the
     * layout as "g" / "l"; the current one reads ratios (0.6) and "grid" / "list".
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>|null the converted data, null when nothing changed
     */
    public static function convertPreferences(array $data): ?array
    {
        $changed = false;

        $data = self::convertLayout($data, $changed);
        foreach (self::DISPLAY_KEYS as $displayKey) {
            if (!is_array($data[$displayKey] ?? null)) {
                continue;
            }
            $display = self::convertLayout($data[$displayKey], $changed);

            if (is_array($display['previewOptions'] ?? null)) {
                foreach (self::RATIO_KEYS as $ratioKey) {
                    $value = $display['previewOptions'][$ratioKey] ?? null;
                    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                        continue;
                    }
                    $value = (float) $value;
                    if ($value <= 1) {
                        continue;
                    }
                    $display['previewOptions'][$ratioKey] = max(self::MIN_RATIO, min(self::MAX_RATIO, round($value / 100, 2)));
                    $changed = true;
                }
            }

            $data[$displayKey] = $display;
        }

        return $changed ? $data : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function convertLayout(array $data, bool &$changed): array
    {
        $layout = $data['layout'] ?? null;
        if (is_string($layout) && isset(self::LAYOUTS[$layout])) {
            $data['layout'] = self::LAYOUTS[$layout];
            $changed = true;
        }

        return $data;
    }
}
