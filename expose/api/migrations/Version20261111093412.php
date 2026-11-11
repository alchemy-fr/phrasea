<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111093412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hash the passwords of the publications and profiles';
    }

    public function up(Schema $schema): void
    {
        foreach (['publication', 'publication_profile'] as $table) {
            $rows = $this->connection->fetchAllAssociative(sprintf('SELECT id, config_security_options FROM %s', $table));
            foreach ($rows as $row) {
                $options = self::hashPassword($row['config_security_options']);
                if (null !== $options) {
                    $this->addSql(sprintf('UPDATE %s SET config_security_options = :options WHERE id = :id', $table), [
                        'options' => $options,
                        'id' => $row['id'],
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The passwords cannot be unhashed.');
    }

    /**
     * @return string|null the security options with the password hashed, null when there is nothing to hash
     */
    public static function hashPassword(?string $securityOptions): ?string
    {
        if (null === $securityOptions || '' === $securityOptions) {
            return null;
        }

        $options = json_decode($securityOptions, true);
        $password = is_array($options) ? ($options['password'] ?? null) : null;
        if (!is_string($password) || '' === $password
            || str_starts_with($password, '$2y$') || str_starts_with($password, '$argon2')) {
            return null;
        }

        $options['password'] = password_hash($password, PASSWORD_DEFAULT);

        return json_encode($options, JSON_THROW_ON_ERROR);
    }
}
