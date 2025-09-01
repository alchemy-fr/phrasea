<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests;

use Alchemy\CoreBundle\Util\LocaleUtil;
use PHPUnit\Framework\TestCase;

class LocaleUtilTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testBestLocale(?string $expectedLocale, array $available, array $locales): void
    {
        $this->assertEquals($expectedLocale, LocaleUtil::getBestLocale($available, $locales));
    }

    public function getCases(): array
    {
        $availableLocales = [
            'fr',
            'fr_FR',
            'en_US',
            'en_UK',
        ];

        return [
            [null, [], ['fr']],
            [null, [], ['fr', 'en']],
            [null, [], ['fr_FR']],
            ['fr_FR', $availableLocales, ['fr_FR']],
            ['fr', $availableLocales, ['fr_CA']],
            ['en_US', $availableLocales, ['en']],
            ['en_UK', $availableLocales, ['en', 'en_UK']],
            ['en_US', $availableLocales, ['en', 'en_US']],
            ['en_UK', $availableLocales, ['en_UK']],
            ['en_UK', $availableLocales, ['en_UK', 'en']],
            ['en_US', $availableLocales, ['en_US']],
            ['en_US', $availableLocales, ['en_US', 'en']],
            ['en_US', $availableLocales, ['en_DD']],
            ['en_US', $availableLocales, ['en_DD', 'en']],
            ['en_US', $availableLocales, ['en_DD', 'fr']],
            [null, $availableLocales, ['']],
        ];
    }
}
