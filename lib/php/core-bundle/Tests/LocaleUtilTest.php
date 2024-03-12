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
        $locales = [
            'fr',
            'fr_FR',
            'en_US',
            'en_UK',
        ];

        return [
            [null, [], ['fr']],
            [null, [], ['fr_FR']],
            ['fr_FR', $locales, ['fr_FR']],
            ['fr', $locales, ['fr_CA']],
            ['en_US', $locales, ['en']],
            ['en_UK', $locales, ['en_UK']],
            ['en_US', $locales, ['en_US']],
            ['en_US', $locales, ['en_DD']],
            [null, $locales, ['']],
        ];
    }
}
