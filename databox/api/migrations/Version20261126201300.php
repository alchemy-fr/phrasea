<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126201300 extends AbstractMigration
{
    /**
     * Zero years, months or days, in their stored form as in the ATOM one Version20261124180000 gave them.
     */
    private const string NULL_DATE_PATTERN = <<<'SQL'
        '^(0000-|\d{4}-00-|\d{4}-\d{2}-00)'
        SQL;

    public function getDescription(): string
    {
        return 'Date attributes holding a null date (zero year, month or day) are marked invalid again';
    }

    public function up(Schema $schema): void
    {
        foreach (['attribute', 'template_attribute'] as $table) {
            $this->addSql(sprintf(
                <<<'SQL'
                    UPDATE %s t
                    SET invalid = true
                    FROM attribute_definition d
                    WHERE t.definition_id = d.id
                      AND d.type IN ('date', 'date_time')
                      AND t.invalid = false
                      AND t.value ~ %s
                    SQL,
                $table,
                self::NULL_DATE_PATTERN,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        // Data repair only: these values are not dates, there is nothing to restore.
    }
}
