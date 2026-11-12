<?php

declare(strict_types=1);

namespace App\Automation\Export;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\Action\AbstractAutomationAction;
use Aws\Exception\AwsException;
use League\Flysystem\Ftp\FtpConnectionException;
use League\Flysystem\PhpseclibV3\UnableToAuthenticate as SftpUnableToAuthenticate;
use League\Flysystem\PhpseclibV3\UnableToConnectToSftpHost;
use League\Flysystem\PhpseclibV3\UnableToEstablishAuthenticityOfHost;
use League\Flysystem\PhpseclibV3\UnableToLoadPrivateKey;

/**
 * The remote destination (S3 bucket, SFTP or FTP server) in the configuration of an action:
 * a "protocol" and the options of this protocol under its key, as read by RemoteFilesystemFactory.
 */
final class RemoteDestinationConfig
{
    /**
     * Paths of the secret options, for AutomationActionInterface::getSecretFields().
     */
    final public const array SECRET_FIELDS = [
        's3.secretAccessKey',
        'sftp.password',
        'sftp.privateKey',
        'sftp.passphrase',
        'ftp.password',
    ];

    /**
     * Errors of the S3 API which fail every request alike.
     */
    private const array S3_DESTINATION_ERRORS = [
        'InvalidAccessKeyId',
        'SignatureDoesNotMatch',
        'NoSuchBucket',
        'InvalidBucketName',
        'AuthorizationHeaderMalformed',
        'PermanentRedirect',
    ];

    /**
     * @return array the normalized destination: "protocol" and the options under its key, other protocols dropped
     *
     * @throws ValidationException
     */
    public static function validate(array $config): array
    {
        $protocol = $config['protocol'] ?? null;
        if (!is_string($protocol) || !in_array($protocol, RemoteFilesystemFactory::PROTOCOLS, true)) {
            self::throwViolations(['protocol' => sprintf('The protocol must be one of: %s.', implode(', ', RemoteFilesystemFactory::PROTOCOLS))]);
        }

        $options = $config[$protocol] ?? [];
        if (!is_array($options)) {
            self::throwViolations([$protocol => 'This value should be an object.']);
        }

        return [
            'protocol' => $protocol,
            $protocol => match ($protocol) {
                RemoteFilesystemFactory::PROTOCOL_S3 => self::validateS3($options),
                RemoteFilesystemFactory::PROTOCOL_SFTP => self::validateSftp($options),
                RemoteFilesystemFactory::PROTOCOL_FTP => self::validateFtp($options),
            },
        ];
    }

    /**
     * @return string|null the error when the destination itself cannot be used
     */
    public static function getDestinationError(\Throwable $e): ?string
    {
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof FtpConnectionException
                || $cause instanceof UnableToConnectToSftpHost
                || $cause instanceof SftpUnableToAuthenticate
                || $cause instanceof UnableToEstablishAuthenticityOfHost
                || $cause instanceof UnableToLoadPrivateKey
            ) {
                return sprintf('Destination unavailable: %s', $cause->getMessage());
            }

            if ($cause instanceof AwsException
                && ($cause->isConnectionError() || in_array($cause->getAwsErrorCode(), self::S3_DESTINATION_ERRORS, true))
            ) {
                return sprintf('Destination unavailable: %s', $cause->getAwsErrorMessage() ?? $cause->getAwsErrorCode() ?? $cause->getMessage());
            }
        }

        return null;
    }

    private static function validateS3(array $options): array
    {
        $endpoint = self::getOptionalString($options, 'endpoint', 's3');
        if (null !== $endpoint && (false === filter_var($endpoint, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $endpoint))) {
            self::throwViolations(['s3.endpoint' => 'This value is not a valid URL.']);
        }

        return [
            'bucket' => self::getRequiredOption($options, 'bucket', 's3'),
            'region' => self::getOptionalString($options, 'region', 's3') ?? 'us-east-1',
            'endpoint' => $endpoint,
            'pathStyle' => self::getBool($options, 'pathStyle', false, 's3'),
            'accessKeyId' => self::getRequiredOption($options, 'accessKeyId', 's3'),
            'secretAccessKey' => self::getSecret($options, 'secretAccessKey', 's3'),
        ];
    }

    private static function validateSftp(array $options): array
    {
        $privateKey = self::getSecret($options, 'privateKey', 'sftp');
        if (null !== $privateKey && !str_contains(trim($privateKey), "\n")) {
            self::throwViolations(['sftp.privateKey' => 'Paste the content of the private key.']);
        }

        return [
            'host' => self::getHost($options, 'sftp'),
            'port' => self::getPort($options, 22, 'sftp'),
            'username' => self::getRequiredOption($options, 'username', 'sftp'),
            'password' => self::getSecret($options, 'password', 'sftp'),
            'privateKey' => null !== $privateKey ? trim($privateKey)."\n" : null,
            'passphrase' => self::getSecret($options, 'passphrase', 'sftp'),
            'hostFingerprint' => self::getOptionalString($options, 'hostFingerprint', 'sftp'),
            'root' => self::getOptionalString($options, 'root', 'sftp') ?? '',
        ];
    }

    private static function validateFtp(array $options): array
    {
        return [
            'host' => self::getHost($options, 'ftp'),
            'port' => self::getPort($options, 21, 'ftp'),
            'username' => self::getRequiredOption($options, 'username', 'ftp'),
            'password' => self::getSecret($options, 'password', 'ftp'),
            'ssl' => self::getBool($options, 'ssl', false, 'ftp'),
            'passive' => self::getBool($options, 'passive', true, 'ftp'),
            'root' => self::getOptionalString($options, 'root', 'ftp') ?? '',
        ];
    }

    private static function getRequiredOption(array $options, string $key, string $prefix): string
    {
        return self::getOptionalString($options, $key, $prefix)
            ?? self::throwViolations([$prefix.'.'.$key => 'This value should not be blank.']);
    }

    private static function getOptionalString(array $options, string $key, string $prefix): ?string
    {
        $value = $options[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            self::throwViolations([$prefix.'.'.$key => 'This value should be a string.']);
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * A secret left empty keeps the stored one (see ActionConfigSecretsHandler): never required here.
     */
    private static function getSecret(array $options, string $key, string $prefix): ?string
    {
        $value = $options[$key] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_string($value)) {
            self::throwViolations([$prefix.'.'.$key => 'This value should be a string.']);
        }

        return $value;
    }

    private static function getHost(array $options, string $prefix): string
    {
        $host = self::getRequiredOption($options, 'host', $prefix);
        if (!preg_match('/^[a-z0-9.\-]+$/i', $host) && false === filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            self::throwViolations([$prefix.'.host' => 'This value should be a host name or an IP address (without scheme nor port).']);
        }

        return trim($host, '[]');
    }

    private static function getPort(array $options, int $default, string $prefix): int
    {
        $port = $options['port'] ?? null;
        if (null === $port || '' === $port) {
            return $default;
        }
        if (is_string($port) && ctype_digit($port)) {
            $port = (int) $port;
        }
        if (!is_int($port) || $port < 1 || $port > 65535) {
            self::throwViolations([$prefix.'.port' => 'This value should be a port number.']);
        }

        return $port;
    }

    private static function getBool(array $options, string $key, bool $default, string $prefix): bool
    {
        $value = $options[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (!is_bool($value)) {
            self::throwViolations([$prefix.'.'.$key => 'This value should be a boolean.']);
        }

        return $value;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function throwViolations(array $errors): never
    {
        AbstractAutomationAction::throwViolations($errors);
    }
}
