<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * Split the legacy attribute_definition.initial_values JSON initializers:
 * - `{"type": "metadata", "value": "TAG"}`  -> appended to read_from_metadata
 * - `{"type": "template", "value": "TWIG"}` -> stored as raw Twig in initial_values.
 */
final class Version20260730090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move metadata initializers to read_from_metadata and unwrap template initializers to raw Twig';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, initial_values, read_from_metadata FROM attribute_definition WHERE initial_values IS NOT NULL'
        );

        foreach ($rows as $row) {
            $initialValues = json_decode((string) $row['initial_values'], true);
            if (!is_array($initialValues)) {
                continue;
            }

            $readFromMetadata = json_decode((string) ($row['read_from_metadata'] ?? ''), true);
            $readFromMetadata = is_array($readFromMetadata) ? $readFromMetadata : [];

            $newInitialValues = [];

            foreach ($initialValues as $locale => $formula) {
                $decoded = json_decode((string) $formula, true);
                if (!is_array($decoded) || !isset($decoded['type'], $decoded['value'])) {
                    // Unknown shape (e.g. already migrated): keep as-is.
                    $newInitialValues[$locale] = $formula;
                    continue;
                }

                if ('metadata' === $decoded['type']) {
                    if (!in_array($decoded['value'], $readFromMetadata, true)) {
                        $readFromMetadata[] = $decoded['value'];
                    }
                } elseif ('template' === $decoded['type']) {
                    $newInitialValues[$locale] = $decoded['value'];
                } else {
                    $newInitialValues[$locale] = $formula;
                }
            }

            $this->connection->executeStatement(
                'UPDATE attribute_definition SET initial_values = CAST(:iv AS JSON), read_from_metadata = CAST(:rfm AS JSON) WHERE id = CAST(:id AS UUID)',
                [
                    'iv' => [] === $newInitialValues ? null : json_encode($newInitialValues),
                    'rfm' => [] === $readFromMetadata ? null : json_encode(array_values($readFromMetadata)),
                    'id' => $row['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // The flat, locale-agnostic read_from_metadata list cannot faithfully reconstruct the
        // original per-locale metadata initializers, so this data migration is not reversible.
        throw new IrreversibleMigration('Cannot restore the legacy initial_values format from read_from_metadata.');
    }
}
