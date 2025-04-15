<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\MigrationKeycloakInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250415153255 extends AbstractMigration implements MigrationKeycloakInterface
{
    private KeycloakManager $keycloakManager;
    private array $symfonyApplications;
    private array $frontendApplications;

    public function setKeycloakManager(KeycloakManager $keycloakManager): void
    {
        $this->keycloakManager = $keycloakManager;
    }

    public function setSymfonyApplications(array $symfonyApplications): void
    {
        $this->symfonyApplications = $symfonyApplications;
    }

    public function setFrontendApplications(array $frontendApplications): void
    {
        $this->frontendApplications = $frontendApplications;
    }
    
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->keycloakManager->putRealm([
            'smtpServer' => [
                'auth' => getenv('EMAIL_USER') ? 'true' : '',
                'from' => getenv('MAIL_FROM') ?: 'noreply@phrasea.io',
                'fromDisplayName' => 'Phrasea',
                'host' => getenv('EMAIL_HOST_RELAY'),
                'port' => getenv('EMAIL_HOST_PORT') ?? '587',
                'replyTo' => '',
                'ssl' => 'false',
                'starttls' => 'false',
                'user' => getenv('EMAIL_USER') ?? null,
                'password' => getenv('EMAIL_SECRET') ?? null,
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
