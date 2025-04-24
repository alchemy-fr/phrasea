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
            'registrationAllowed'       => $this->getBooleanEnv('KC_REALM_LOGIN_REGISTRATION_ALLOWED', false),
            'resetPasswordAllowed'      => $this->getBooleanEnv('KC_REALM_LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe'                => $this->getBooleanEnv('KC_REALM_LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed'     => $this->getBooleanEnv('KC_REALM_LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail'               => $this->getBooleanEnv('KC_REALM_LOGIN_VERIFY_EMAIL_ALLOWED', false),
            'registrationEmailAsUsername' => $this->getBooleanEnv('KC_REALM_LOGIN_EMAIL_AS_USERNAME', false),
            'editUsernameAllowed'       => $this->getBooleanEnv('KC_REALM_LOGIN_EDIT_USERNAME', false),            
             'bruteForceProtected'       => true,
            'failureFactor'             => '30',   
            'bruteForceStrategy'        => 'MULTIPLE',
            'permanentLockout'          => false,
            'waitIncrementSeconds'      => '60',
            'maxFailureWaitSeconds'     => '900',
            'maxDeltaTimeSeconds'       => '43200',
            'quickLoginCheckMilliSeconds' => '1000',
            'minimumQuickLoginWaitSeconds' => '60',
            'eventsEnabled'                 => $this->getBooleanEnv('KC_REALM_USER_EVENT_ENABLED', false),
            'eventsExpiration'              => getenv('KC_REALM_USER_EVENT_EXPIRATION') ?: '604800',
            'eventsListeners'               => ['jboss-logging'],
            'adminEventsEnabled'           => $this->getBooleanEnv('KC_REALM_ADMIN_EVENT_ENABLED', false),
            'adminEventsDetailsEnabled'    => true,      
            'ssoSessionIdleTimeout'     => getenv('KC_REALM_SSO_SESSION_IDLE_TIMEOUT') ?: '1800',
            'clientSessionIdleTimeout'  => getenv('KC_REALM_CLIENT_SESSION_IDLE_TIMEOUT') ?: '1800',
            'offlineSessionIdleTimeout' => getenv('KC_REALM_OFFLINE_SESSION_IDLE_TIMEOUT') ?: '2592000',
            'internationalizationEnabled' => true,
            'supportedLocales'          => (getenv('KC_REALM_SUPPORTED_LOCALES') != null) ? explode(',', getenv('KC_REALM_SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale'             => getenv('KC_REALM_DEFAULT_LOCALE') ?: 'en',
            'smtpServer' => [
                'auth' => getenv('EMAIL_USER') ? 'true' : '',
                'from' => getenv('MAIL_FROM') ?: 'noreply@phrasea.io',
                'fromDisplayName' => 'Phrasea',
                'host' => getenv('MAILER_HOST'),
                'port' => getenv('MAILER_PORT') ?? '587',
                'replyTo' => '',
                'ssl' => 'false',
                'starttls' => 'false',
                'user' => getenv('MAILER_USER') ?? null,
                'password' => getenv('MAILER_PASSWORD') ?? null,
            ],
            'attributes' => [
                'adminEventsExpiration' => getenv('KC_REALM_ADMIN_EVENT_EXPIRATION') ?: '604800',
            ]
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
