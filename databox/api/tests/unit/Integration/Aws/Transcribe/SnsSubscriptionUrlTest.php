<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Aws\Transcribe;

use App\Integration\Aws\Transcribe\Consumer\AwsTranscribeEventHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SnsSubscriptionUrlTest extends TestCase
{
    /**
     * The (unauthenticated) SNS endpoint makes the worker request the subscription URL.
     */
    #[DataProvider('getSubscribeUrls')]
    public function testSnsSubscriptionUrlMustBeAnAwsOne(string $url, bool $expected): void
    {
        $this->assertSame($expected, AwsTranscribeEventHandler::isSnsUrl($url));
    }

    public static function getSubscribeUrls(): array
    {
        return [
            ['https://sns.eu-west-3.amazonaws.com/?Action=ConfirmSubscription&Token=x', true],
            ['https://sns.cn-north-1.amazonaws.com.cn/?Action=ConfirmSubscription', true],
            ['http://sns.eu-west-3.amazonaws.com/?Action=ConfirmSubscription', false],
            ['https://sns.eu-west-3.amazonaws.com.evil.test/', false],
            ['https://evil.test/sns.eu-west-3.amazonaws.com/', false],
            ['https://user@sns.eu-west-3.amazonaws.com/', false],
            ['https://sns.eu-west-3.amazonaws.com:8443/', false],
            ['http://169.254.169.254/latest/meta-data/', false],
            ['http://elasticsearch:9200/_search', false],
        ];
    }
}
