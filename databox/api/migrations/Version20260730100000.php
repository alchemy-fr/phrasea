<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Re-shape file_metadata.metadata from a flat, wrapped map:
 *     { "File:MIMEType": { "name": "MIMEType", "values": ["image/jpeg"] } }
 * to a namespace-grouped, unwrapped map:
 *     { "File": { "MIMEType": ["image/jpeg"] } }
 *
 * The stored checksum is recomputed to stay consistent with the new serialization
 * (otherwise every file would report metadataHasChanged() === true).
 */
final class Version20260730100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group file metadata by namespace and drop the name/values wrapper';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->iterateAssociative('SELECT id, metadata FROM file_metadata') as $row) {
            $flat = json_decode((string) $row['metadata'], true);
            if (!is_array($flat)) {
                continue;
            }

            $nested = [];
            foreach ($flat as $key => $data) {
                if (!is_array($data)) {
                    continue;
                }
                $values = $data['values'] ?? [];

                [$group, $tag] = array_pad(explode(':', (string) $key, 2), 2, null);
                if (null === $tag) {
                    // Defensive: keep a non-namespaced key as-is (not expected for exiftool ids).
                    $nested[$key] = $values;
                } else {
                    $nested[$group][$tag] = $values;
                }
            }

            $this->update((string) $row['id'], $nested);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->connection->iterateAssociative('SELECT id, metadata FROM file_metadata') as $row) {
            $nested = json_decode((string) $row['metadata'], true);
            if (!is_array($nested)) {
                continue;
            }

            $flat = [];
            foreach ($nested as $group => $tags) {
                if (!is_array($tags)) {
                    continue;
                }
                foreach ($tags as $tag => $values) {
                    $flat[$group.':'.$tag] = [
                        'name' => $tag,
                        'values' => $values,
                    ];
                }
            }

            $this->update((string) $row['id'], $flat);
        }
    }

    private function update(string $id, array $metadata): void
    {
        $this->connection->executeStatement(
            'UPDATE file_metadata SET metadata = CAST(:m AS JSON), checksum = :c WHERE id = CAST(:id AS UUID)',
            [
                'm' => json_encode($metadata),
                'c' => hash('sha256', serialize($metadata)),
                'id' => $id,
            ]
        );
    }
}
