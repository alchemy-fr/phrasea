<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\ContentDispositionUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ContentDispositionUtilTest extends TestCase
{
    #[DataProvider('getFilenames')]
    public function testMake(string $filename, string $expected): void
    {
        $this->assertSame($expected, ContentDispositionUtil::make(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
    }

    public static function getFilenames(): array
    {
        return [
            'token' => ['Colors.csv', 'attachment; filename=Colors.csv'],
            'space' => ['My colors.csv', 'attachment; filename="My colors.csv"'],
            'quotes and header separators' => [
                'a"; filename=evil.html; x=".csv',
                'attachment; filename="a\"; filename=evil.html; x=\".csv"',
            ],
            'non-ASCII' => ['Été.csv', "attachment; filename=Ete.csv; filename*=utf-8''%C3%89t%C3%A9.csv"],
            'percent' => ['100%.csv', "attachment; filename=100.csv; filename*=utf-8''100%25.csv"],
            'path separators' => ['../a\\b.csv', 'attachment; filename=..-a-b.csv'],
            'control characters' => ["a\r\nb.csv", "attachment; filename=a-b.csv; filename*=utf-8''a%0D%0Ab.csv"],
            'nothing transliterable' => ['☃.csv', "attachment; filename=file.csv; filename*=utf-8''%E2%98%83.csv"],
            'empty' => ['', 'attachment; filename=file'],
        ];
    }

    public function testInline(): void
    {
        $this->assertSame('inline; filename=terms-ws-v2.pdf', ContentDispositionUtil::make(HeaderUtils::DISPOSITION_INLINE, 'terms-ws-v2.pdf'));
    }
}
