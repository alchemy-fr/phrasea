<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230920160828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate from "array" to "json" Doctrine type';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_definition ALTER fallback TYPE JSON USING fallback::json');
        $this->addSql('COMMENT ON COLUMN attribute_definition.fallback IS NULL');
        $this->addSql('ALTER TABLE file ALTER alternate_urls TYPE JSON USING alternate_urls::json');
        $this->addSql('COMMENT ON COLUMN file.alternate_urls IS NULL');

        $connection = $this->connection;

        foreach ([
            ['attribute_definition', 'fallback'],
            ['file', 'alternate_urls'],
                 ] as $t) {
            [$table, $column] = $t;
            $rows = $connection->fetchAllAssociative(sprintf('SELECT "id", "%s" FROM "%s"', $column, $table));

            foreach ($rows as $row) {
                $connection->executeStatement(sprintf('UPDATE "%s" SET "%s" = :encodedData WHERE id = :id', $table, $column), [
                    'encodedData' => json_encode(unserialize($row[$column])),
                    'id' => $row['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        throw new \InvalidArgumentException('Cannot revert');
    }
}
