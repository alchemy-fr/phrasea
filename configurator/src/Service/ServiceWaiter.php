<?php

namespace App\Service;

use App\Util\HttpClientUtil;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ServiceWaiter
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    public function waitForService(
        OutputInterface $output,
        string $url,
        ?int $timeout = null,
        int $waitMicroseconds = 200_000,
        array $successCodes = HttpClientUtil::DEFAULT_SUCCESS_CODES,
        array $unexpectedCodes = HttpClientUtil::DEFAULT_UNEXPECTED_CODES,
    ): void {
        HttpClientUtil::waitForHostHttp(
            $output,
            $this->client,
            $url,
            $timeout,
            $waitMicroseconds,
            $successCodes,
            $unexpectedCodes
        );
    }
}
