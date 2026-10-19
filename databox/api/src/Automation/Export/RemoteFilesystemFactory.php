<?php

declare(strict_types=1);

namespace App\Automation\Export;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

/**
 * Builds the filesystem of a remote export destination from the (decrypted) configuration
 * of a RemoteExportAction.
 *
 * The connections are lazy: nothing is reached before the first operation.
 */
class RemoteFilesystemFactory
{
    final public const string PROTOCOL_S3 = 's3';
    final public const string PROTOCOL_SFTP = 'sftp';
    final public const string PROTOCOL_FTP = 'ftp';

    final public const array PROTOCOLS = [
        self::PROTOCOL_S3,
        self::PROTOCOL_SFTP,
        self::PROTOCOL_FTP,
    ];

    private const int TIMEOUT = 30;

    public function create(array $config): FilesystemOperator
    {
        $protocol = $config['protocol'] ?? null;

        return new Filesystem($this->createAdapter($protocol, $config[$protocol] ?? []));
    }

    protected function createAdapter(?string $protocol, array $options): FilesystemAdapter
    {
        return match ($protocol) {
            self::PROTOCOL_S3 => $this->createS3Adapter($options),
            self::PROTOCOL_SFTP => $this->createSftpAdapter($options),
            self::PROTOCOL_FTP => $this->createFtpAdapter($options),
            default => throw new \InvalidArgumentException(sprintf('Unsupported export protocol "%s"', $protocol)),
        };
    }

    private function createS3Adapter(array $options): AwsS3V3Adapter
    {
        if (empty($options['secretAccessKey'])) {
            throw new \InvalidArgumentException('The S3 secret access key is not defined');
        }

        $clientOptions = [
            'version' => 'latest',
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret' => $options['secretAccessKey'],
            ],
            'use_path_style_endpoint' => (bool) ($options['pathStyle'] ?? false),
            'http' => [
                'connect_timeout' => self::TIMEOUT,
            ],
        ];
        if (!empty($options['endpoint'])) {
            $clientOptions['endpoint'] = $options['endpoint'];
        }

        return new AwsS3V3Adapter(new S3Client($clientOptions), $options['bucket']);
    }

    private function createSftpAdapter(array $options): SftpAdapter
    {
        $privateKey = $options['privateKey'] ?? null;
        // Given a single line, the connection provider would read it as a path on the worker
        if (null !== $privateKey && !str_contains($privateKey, "\n")) {
            throw new \InvalidArgumentException('The SFTP private key must be the content of the key');
        }

        $provider = new SftpConnectionProvider(
            host: $options['host'],
            username: $options['username'],
            password: $options['password'] ?? null,
            privateKey: $privateKey,
            passphrase: $options['passphrase'] ?? null,
            port: (int) ($options['port'] ?? 22),
            timeout: self::TIMEOUT,
            maxTries: 1,
            hostFingerprint: $options['hostFingerprint'] ?? null,
        );

        return new SftpAdapter($provider, $options['root'] ?? '', disconnectOnDestruct: true);
    }

    private function createFtpAdapter(array $options): FtpAdapter
    {
        return new FtpAdapter(FtpConnectionOptions::fromArray([
            'host' => $options['host'],
            'port' => (int) ($options['port'] ?? 21),
            'username' => $options['username'],
            'password' => $options['password'] ?? '',
            'root' => $options['root'] ?? '',
            'ssl' => (bool) ($options['ssl'] ?? false),
            'passive' => (bool) ($options['passive'] ?? true),
            'timeout' => self::TIMEOUT,
        ]));
    }
}
