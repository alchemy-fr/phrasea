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
            'displayName'               => 'Phrasea Auth',
            'displayNameHtml'           => '<div class="kc-logo-text"><span>Phrasea Auth</span></div>',
            'registrationAllowed'       => $this->getBooleanEnv('KEYCLOAK_LOGIN_REGISTRATION_ALLOWED', false),
            'resetPasswordAllowed'      => $this->getBooleanEnv('KEYCLOAK_LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe'                => $this->getBooleanEnv('KEYCLOAK_LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed'     => $this->getBooleanEnv('KEYCLOAK_LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail'               => $this->getBooleanEnv('KEYCLOAK_LOGIN_VERIFY_EMAIL_ALLOWED', false),
            'registrationEmailAsUsername' => $this->getBooleanEnv('KEYCLOAK_LOGIN_EMAIL_AS_USERNAME', false),
            'editUsernameAllowed'       => $this->getBooleanEnv('KEYCLOAK_LOGIN_EDIT_USERNAME', false),
            'bruteForceProtected'       => $this->getBooleanEnv('KEYCLOAK_SECURITY_DETECTION_BRUTE_FORCE_ENABLED', false),
            'ssoSessionIdleTimeout'     => getenv('KEYCLOAK_SSO_SESSION_IDLE_TIMEOUT') ?: '1800',
            'clientSessionIdleTimeout'  => getenv('KEYCLOAK_CLIENT_SESSION_IDLE_TIMEOUT') ?: '1800',
            'offlineSessionIdleTimeout' => getenv('KEYCLOAK_OFFLINE_SESSION_IDLE_TIMEOUT') ?: '2592000',
            'internationalizationEnabled' => $this->getBooleanEnv('KEYCLOAK_LOCALISATION_ENABLED', true),
            'supportedLocales'          => (getenv('KEYCLOAK_SUPPORTED_LOCALES') != null) ? explode(',', getenv('KEYCLOAK_SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale'             => getenv('KEYCLOAK_DEFAULT_LOCALE') ?: 'en',
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

    private function getBooleanEnv(string $name, bool $defaultValue= false): bool
    {
        switch (getenv($name)) {
            case 'true':
                return true;
                break;
            case 'false':
                return false;
                break;
            default:
                return $defaultValue;
                break;    
        }
    }
}
