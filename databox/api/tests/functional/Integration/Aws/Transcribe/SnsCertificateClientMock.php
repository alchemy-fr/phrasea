<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Aws\Transcribe;

use App\Integration\Aws\Sns\SnsCertificateClient;

/**
 * Serves the certificates set by the test instead of downloading them from AWS.
 */
class SnsCertificateClientMock extends SnsCertificateClient
{
    /**
     * @var array<string, string> PEM certificates by URL
     */
    public static array $certificates = [];

    public function __construct()
    {
    }

    #[\Override]
    public function __invoke(string $url): string
    {
        return self::$certificates[$url] ?? throw new \InvalidArgumentException(sprintf('No certificate at "%s"', $url));
    }
}
