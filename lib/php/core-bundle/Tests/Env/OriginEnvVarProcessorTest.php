<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Env;

use Alchemy\CoreBundle\Env\OriginEnvVarProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OriginEnvVarProcessorTest extends TestCase
{
    #[DataProvider('getCases')]
    public function testGetEnv(string $value, string $expectedOrigin): void
    {
        $processor = new OriginEnvVarProcessor();

        $this->assertSame($expectedOrigin, $processor->getEnv('origin', 'FOO_URL', function (string $name) use ($value): string {
            $this->assertSame('FOO_URL', $name);

            return $value;
        }));
    }

    public static function getCases(): array
    {
        return [
            ['https://databox.phrasea.local', 'https://databox.phrasea.local'],
            ['https://databox.phrasea.local/', 'https://databox.phrasea.local'],
            ['https://databox.phrasea.local/some/path?query=1#hash', 'https://databox.phrasea.local'],
            ['HTTPS://Databox.Phrasea.LOCAL/Path', 'https://databox.phrasea.local'],
            ['https://databox.phrasea.local:443', 'https://databox.phrasea.local'],
            ['http://databox.phrasea.local:80/', 'http://databox.phrasea.local'],
            ['https://databox.phrasea.local:8443/', 'https://databox.phrasea.local:8443'],
            ['http://localhost:3000', 'http://localhost:3000'],
            [' https://databox.phrasea.local ', 'https://databox.phrasea.local'],
            ['http://define-me', 'http://define-me'],
            ['', ''],
            ['databox.phrasea.local', ''],
            ['/relative/path', ''],
            ['https://', ''],
            ['http:///path', ''],
        ];
    }

    public function testProvidedTypes(): void
    {
        $this->assertSame(['origin' => 'string'], OriginEnvVarProcessor::getProvidedTypes());
    }
}
