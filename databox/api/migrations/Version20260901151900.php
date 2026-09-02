<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

/**
 * Moves the duplicate file IDs stored in file.analysis JSON payloads into the
 * file_duplicate table. Only files that still exist and are the source of a
 * non-trashed asset are kept as duplicates; the analysis error is removed when
 * no duplicate is left, and assets quarantined only because of dropped
 * duplicates are released.
 *
 * The analysis `hash` is preserved so unchanged configs do not re-trigger a
 * mass re-analysis.
 *
 * Note: assets de-quarantined by this migration require an Elasticsearch
 * reindex afterwards (bin/console fos:elastica:populate).
 */
final class Version20260901151900 extends AbstractMigration
{
    private const int LEVEL_ERROR = 3;
    private const int ASSET_STATUS_ACCEPTED = 0;
    private const int ASSET_STATUS_QUARANTINED = 2;

    public function getDescription(): string
    {
        return 'Move analysis duplicates from the file.analysis payload to the file_duplicate table (payload stripping is irreversible)';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->iterateAssociative(<<<'SQL'
            SELECT id, analysis FROM file
            WHERE analysis IS NOT NULL AND analysis::text LIKE '%"duplicates"%'
            SQL) as $row) {
            $analysis = json_decode((string) $row['analysis'], true);
            if (!is_array($analysis) || empty($analysis['results'])) {
                continue;
            }

            $changed = false;
            $hasError = false;
            foreach ($analysis['results'] as $i => $result) {
                $duplicates = $result['output']['duplicates'] ?? null;
                if (!is_array($duplicates)) {
                    foreach ($result['output']['messages'] ?? [] as $message) {
                        if ((int) ($message[0] ?? 0) >= self::LEVEL_ERROR) {
                            $hasError = true;
                        }
                    }
                    continue;
                }

                $changed = true;
                unset($analysis['results'][$i]['output']['duplicates']);

                $kept = 0;
                foreach (array_unique($duplicates) as $duplicateFileId) {
                    $isActiveSource = $this->connection->fetchOne(
                        'SELECT 1 FROM asset WHERE source_id = CAST(:id AS UUID) AND deleted_at IS NULL LIMIT 1',
                        ['id' => $duplicateFileId],
                    );
                    if (false === $isActiveSource) {
                        continue;
                    }

                    ++$kept;
                    $this->connection->executeStatement(<<<'SQL'
                        INSERT INTO file_duplicate (id, file_id, duplicate_file_id, analyzer, created_at)
                        VALUES (CAST(:id AS UUID), CAST(:file AS UUID), CAST(:dup AS UUID), :analyzer, NOW())
                        ON CONFLICT DO NOTHING
                        SQL, [
                        'id' => Uuid::uuid4()->toString(),
                        'file' => $row['id'],
                        'dup' => $duplicateFileId,
                        'analyzer' => (string) ($result['name'] ?? ''),
                    ]);
                }

                $messages = [];
                foreach ($result['output']['messages'] ?? [] as $message) {
                    if (str_starts_with((string) ($message[1] ?? ''), 'duplicate_')) {
                        if (0 === $kept) {
                            continue;
                        }
                        $payload = is_array($message[2] ?? null) ? $message[2] : [];
                        $message[2] = array_merge($payload, ['count' => $kept]);
                    }
                    if ((int) ($message[0] ?? 0) >= self::LEVEL_ERROR) {
                        $hasError = true;
                    }
                    $messages[] = $message;
                }
                if (empty($messages)) {
                    unset($analysis['results'][$i]['output']['messages']);
                } else {
                    $analysis['results'][$i]['output']['messages'] = $messages;
                }
            }

            if (!$changed) {
                continue;
            }

            if ('failed' === ($analysis['status'] ?? null) && !$hasError) {
                $analysis['status'] = 'success';
                $this->connection->executeStatement(
                    'UPDATE asset SET status = :accepted WHERE source_id = CAST(:fileId AS UUID) AND status = :quarantined',
                    [
                        'accepted' => self::ASSET_STATUS_ACCEPTED,
                        'quarantined' => self::ASSET_STATUS_QUARANTINED,
                        'fileId' => $row['id'],
                    ],
                );
            }

            $this->connection->executeStatement(
                'UPDATE file SET analysis = CAST(:analysis AS JSON) WHERE id = CAST(:id AS UUID)',
                [
                    'analysis' => json_encode($analysis, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }

        $this->write('Assets de-quarantined by this migration require an Elasticsearch reindex (fos:elastica:populate).');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The duplicates stripped from the analysis payloads cannot be restored.');
    }
}
