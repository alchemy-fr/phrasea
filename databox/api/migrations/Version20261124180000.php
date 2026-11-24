<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124180000 extends AbstractMigration
{
    /**
     * The ISO 8601 extended forms accepted by DateUtil::normalizeDate() through 'Y-m-d\TH:i:s.uP':
     * 1 to 6 fractional digits and a mandatory time zone (Z, +HH:MM or +HHMM).
     */
    private const string FRACTIONAL_DATE_PATTERN = <<<'SQL'
        '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{1,6}(Z|[+-]\d{2}:\d{2}|[+-]\d{4})$'
        SQL;

    /**
     * Same result as DateUtil::normalizeDate($value)->format(DATE_ATOM) for the values matching the pattern above:
     * fractional seconds dropped, +HHMM turned into +HH:MM and Z into +00:00.
     */
    private const string ATOM_EXPRESSION = <<<'SQL'
        regexp_replace(
            regexp_replace(
                regexp_replace(t.value, '^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\.\d+', '\1'),
                '(T\d{2}:\d{2}:\d{2}[+-]\d{2})(\d{2})$', '\1:\2'
            ),
            '(T\d{2}:\d{2}:\d{2})Z$', '\1+00:00'
        )
        SQL;

    public function getDescription(): string
    {
        return 'Date attributes stored as invalid because of fractional seconds (ISO 8601 extended) are normalized to ATOM';
    }

    public function up(Schema $schema): void
    {
        foreach (['attribute', 'template_attribute'] as $table) {
            $this->addSql(sprintf(
                <<<'SQL'
                    UPDATE %s t
                    SET value = %s, invalid = false
                    FROM attribute_definition d
                    WHERE t.definition_id = d.id
                      AND d.type IN ('date', 'date_time')
                      AND t.invalid = true
                      AND t.value ~ %s
                    SQL,
                $table,
                self::ATOM_EXPRESSION,
                self::FRACTIONAL_DATE_PATTERN,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        // Data repair only: the canonical values are valid dates, there is nothing to restore.
    }
}
