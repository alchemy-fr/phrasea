<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class HttpClientUtil
{
    public const int DEFAULT_TIMEOUT = 60;
    public const array DEFAULT_UNEXPECTED_CODES = [500];
    public const array DEFAULT_SUCCESS_CODES = [200];

    public static function debugError(callable $handler, ?int $ignoreHttpCode = null, ?array $data = null): mixed
    {
        try {
            return $handler();
        } catch (ClientExceptionInterface $e) {
            if (null !== $ignoreHttpCode && $ignoreHttpCode === $e->getResponse()->getStatusCode()) {
                return null;
            }

            $error = $e->getResponse()->getContent(false);

            throw new \InvalidArgumentException(sprintf('%s: %s%s', $e->getMessage(), $error, null !== $data ? ' (with data: '.print_r($data, true).')' : ''), 0, $e);
        }
    }

    public static function isHostReady(string $host, int $port, float $timeout = 1.0): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    public static function waitForHostPort(OutputInterface $output, string $host, int $port, ?int $timeout = null, int $waitMicroseconds = 200_000): void
    {
        $timeout = self::resolveTimeout($timeout);
        $progressIndicator = self::createProgressIndicator($output, sprintf('Waiting for host %s:%d to be ready...', $host, $port));
        $attempts = 0;
        $maxAttempts = (int) ($timeout / ($waitMicroseconds / 1_000_000));
        while ($attempts < $maxAttempts) {
            if (self::isHostReady($host, $port)) {
                $progressIndicator->finish('Ready.');

                return;
            }

            ++$attempts;
            $progressIndicator->advance();
            usleep($waitMicroseconds);
        }

        throw new \RuntimeException(sprintf('Host %s:%d is not ready after %d attempts.', $host, $port, $maxAttempts));
    }

    public static function waitForHostHttp(
        OutputInterface $output,
        HttpClientInterface $client,
        string $url,
        ?int $timeout = null,
        int $waitMicroseconds = 200_000,
        array $successCodes = self::DEFAULT_SUCCESS_CODES,
        array $unexpectedCodes = self::DEFAULT_UNEXPECTED_CODES,
    ): void {
        $timeout = self::resolveTimeout($timeout);

        if (empty($url)) {
            throw new \InvalidArgumentException('URL is empty.');
        }
        $urlInfo = parse_url($url);
        if (false === $urlInfo || !isset($urlInfo['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid URL "%s".', $url));
        }
        $host = $urlInfo['host'];
        $port = (int) ($urlInfo['port'] ?? ('https' === $urlInfo['scheme'] ? 443 : 80));

        self::waitForHostPort($output, $host, $port, $timeout, $waitMicroseconds);

        $progressIndicator = self::createProgressIndicator($output, sprintf('Waiting for URL %s to be ready...', $url));
        $attempts = 0;
        $maxAttempts = (int) ($timeout / ($waitMicroseconds / 1_000_000));

        while ($attempts < $maxAttempts) {
            $continue = true;
            try {
                $client->request('GET', $url);
                $continue = false;
            } catch (ServerExceptionInterface $e) {
            } catch (ClientExceptionInterface $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (in_array($statusCode, $unexpectedCodes, true)) {
                    throw new \RuntimeException(sprintf('URL "%s" returned unexpected status code %d.', $url, $statusCode), 0, $e);
                }

                if (in_array($statusCode, $successCodes, true)) {
                    $continue = false;
                }
            }

            if ($continue) {
                ++$attempts;
                $progressIndicator->advance();
                usleep($waitMicroseconds);
            } else {
                $progressIndicator->finish('Ready.');

                return;
            }
        }

        throw new \RuntimeException(sprintf('URL "%s" is not reachable after %d attempts.', $url, $maxAttempts));
    }

    private static function createProgressIndicator(OutputInterface $output, string $message): ProgressIndicator
    {
        $progressIndicator = new ProgressIndicator($output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start($message);

        return $progressIndicator;
    }

    private static function resolveTimeout(?int $timeout): int
    {
        $envValue = EnvHelper::getEnvOrThrow('SERVICE_WAIT_TIMEOUT');

        return $timeout ?? ($envValue ? (int) $envValue : self::DEFAULT_TIMEOUT);
    }
}
