<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Gedmo\Sluggable\Util\Urlizer;

use function Symfony\Component\String\u;

/**
 * Backfill the slug of the attribute definitions created before the slug column existed.
 *
 * Used to be done through the ORM in postUp() (re-saving each entity so that the Gedmo
 * sluggable listener computed the slug), which stopped running on a fresh database once
 * the AttributeDefinition entity evolved. The slug is now computed here the same way the
 * listener does (transliterate, urlize with an empty separator, lower case).
 */
final class Version20220404135920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill attribute_definition.slug';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM attribute_definition WHERE slug IS NULL');

        foreach ($rows as $row) {
            $slug = Urlizer::transliterate((string) $row['name'], '');
            $slug = Urlizer::urlize($slug, '');
            $slug = u($slug)->lower()->toString();

            $this->connection->executeStatement('UPDATE attribute_definition SET slug = :slug WHERE id = :id', [
                'slug' => $slug,
                'id' => $row['id'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
