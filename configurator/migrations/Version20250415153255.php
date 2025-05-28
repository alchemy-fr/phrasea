<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\MigrationKeycloakInterface;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

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
            'displayName' => 'Phrasea Auth',
            'displayNameHtml' => '<div class="kc-logo-text"><span>Phrasea Auth</span></div>',
            'registrationAllowed' => $this->getBooleanEnv('KC_REALM_LOGIN_REGISTRATION_ALLOWED', false),
            'resetPasswordAllowed' => $this->getBooleanEnv('KC_REALM_LOGIN_RESET_PASSWORD_ALLOWED', true),
            'rememberMe' => $this->getBooleanEnv('KC_REALM_LOGIN_REMEMBER_ME_ALLOWED', true),
            'loginWithEmailAllowed' => $this->getBooleanEnv('KC_REALM_LOGIN_WITH_EMAIL_ALLOWED', true),
            'verifyEmail' => $this->getBooleanEnv('KC_REALM_LOGIN_VERIFY_EMAIL_ALLOWED', false),
            'registrationEmailAsUsername' => $this->getBooleanEnv('KC_REALM_LOGIN_EMAIL_AS_USERNAME', false),
            'editUsernameAllowed' => $this->getBooleanEnv('KC_REALM_LOGIN_EDIT_USERNAME', false),
            'bruteForceProtected' => true,
            'failureFactor' => '30',
            'bruteForceStrategy' => 'MULTIPLE',
            'permanentLockout' => false,
            'waitIncrementSeconds' => '60',
            'maxFailureWaitSeconds' => '900',
            'maxDeltaTimeSeconds' => '43200',
            'quickLoginCheckMilliSeconds' => '1000',
            'minimumQuickLoginWaitSeconds' => '60',
            'eventsEnabled' => $this->getBooleanEnv('KC_REALM_USER_EVENT_ENABLED', false),
            'eventsExpiration' => getenv('KC_REALM_USER_EVENT_EXPIRATION') ?: '604800',
            'eventsListeners' => ['jboss-logging'],
            'adminEventsEnabled' => $this->getBooleanEnv('KC_REALM_ADMIN_EVENT_ENABLED', false),
            'adminEventsDetailsEnabled' => true,
            'ssoSessionIdleTimeout' => getenv('KC_REALM_SSO_SESSION_IDLE_TIMEOUT') ?: '1800',
            'ssoSessionMaxLifespan' => getenv('KC_REALM_SSO_SESSION_MAX_LIFESPAN') ?: '36000',
            'clientSessionIdleTimeout' => getenv('KC_REALM_CLIENT_SESSION_IDLE_TIMEOUT') ?: '1800',
            'clientSessionMaxLifespan' => getenv('KC_REALM_CLIENT_SESSION_MAX_LIFESPAN') ?: '36000',
            'offlineSessionIdleTimeout' => getenv('KC_REALM_OFFLINE_SESSION_IDLE_TIMEOUT') ?: '2592000',
            'offlineSessionMaxLifespanEnabled' => getenv('KC_REALM_OFFLINE_SESSION_MAX_LIFESPAN') ? true : false,
            'offlineSessionMaxLifespan' => getenv('KC_REALM_OFFLINE_SESSION_MAX_LIFESPAN') ?: '7344000',
            'internationalizationEnabled' => true,
            'supportedLocales' => (null != getenv('KC_REALM_SUPPORTED_LOCALES')) ? explode(',', getenv('KC_REALM_SUPPORTED_LOCALES')) : ['en'],
            'defaultLocale' => getenv('KC_REALM_DEFAULT_LOCALE') ?: 'en',
            'smtpServer' => [
                'auth' => getenv('MAILER_USER') ? true : false,
                'from' => getenv('MAIL_FROM') ?: 'noreply@phrasea.io',
                'fromDisplayName' => getenv('MAIL_FROM_DISPLAY_NAME') ?: 'Phrasea',
                'replyTo' => getenv('MAIL_REPLY_TO') ?: '',
                'replyToDisplayName' => getenv('MAIL_REPLY_TO_DISPLAY_NAME') ?: '',
                'envelopeFrom' => getenv('MAIL_ENVELOPE_FROM') ?: '',
                'host' => getenv('MAILER_HOST'),
                'port' => getenv('MAILER_PORT') ?? '587',
                'ssl' => $this->getBooleanEnv('MAILER_SSL', false),
                'starttls' => $this->getBooleanEnv('MAILER_TLS', false),
                'user' => getenv('MAILER_USER') ?? null,
                'password' => getenv('MAILER_PASSWORD') ?? null,
            ],
            'attributes' => [
                'adminEventsExpiration' => getenv('KC_REALM_ADMIN_EVENT_EXPIRATION') ?: '604800',
            ],
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }

    private function getBooleanEnv(string $name, bool $defaultValue = false): bool
    {
        $val = filter_var(getenv($name), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (null === $val) {
            return $defaultValue;
        }

        return $val;
    }
}
