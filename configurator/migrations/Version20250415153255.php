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
            'registrationAllowed'       => $this->getBooleanEnv('LOGIN_REGISTRATION_ALLOWED', false),
            'resetPasswordAllowed'      => $this->getBooleanEnv('LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe'                => $this->getBooleanEnv('LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed'     => $this->getBooleanEnv('LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail'               => $this->getBooleanEnv('LOGIN_VERIFY_EMAIL_ALLOWED', false),
            'registrationEmailAsUsername' => $this->getBooleanEnv('LOGIN_EMAIL_AS_USERNAME', false),
            'editUsernameAllowed'       => $this->getBooleanEnv('LOGIN_EDIT_USERNAME', false),
            'bruteForceProtected'       => $this->getBooleanEnv('SECURITY_DETECTION_BRUTE_FORCE_ENABLED', false),
            'ssoSessionIdleTimeout'     => getenv('SSO_SESSION_IDLE_TIMEOUT') ?: '1800',
            'clientSessionIdleTimeout'  => getenv('CLIENT_SESSION_IDLE_TIMEOUT') ?: '1800',
            'offlineSessionIdleTimeout' => getenv('OFFLINE_SESSION_IDLE_TIMEOUT') ?: '2592000',
            'internationalizationEnabled' => $this->getBooleanEnv('LOCALISATION_ENABLED', true),
            'supportedLocales'          => (getenv('SUPPORTED_LOCALES') != null) ? explode(',', getenv('SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale'             => getenv('DEFAULT_LOCALE') ?: 'en',
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
