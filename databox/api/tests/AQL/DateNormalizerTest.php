<?php

namespace AQL;

use App\Elasticsearch\AQL\DateNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DateNormalizerTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testDateNormalize(string $expression, string|int $expectedResult): void
    {
        $normalizer = new DateNormalizer();

        try {
            $result = $normalizer->normalizeDate($expression);
            $this->assertEquals($expectedResult, $result);
        } catch (BadRequestHttpException $e) {
            $this->assertEquals($expectedResult, $e->getMessage());
        }
    }

    public function getCases(): array
    {
        return [
            ['YYYY-88-88', 'Invalid date value "YYYY-88-88"'],
            ['9999-88-88', '10006-06-27'],
            ['', 'Invalid date value ""'],
            ['2015', 2015],
            ['2123-11-05 15:16:17', '2123-11-05T15:16:17+0000'],
            ['2123-11-05T15:16:17', '2123-11-05T15:16:17+0000'],
            ['2123-11-05T15:16:17+10:32', '2123-11-05T15:16:17+1032'],
            ['2123-11-05T15:16:17+1032', '2123-11-05T15:16:17+1032'],
            ['2123-11-05T15:16', '2123-11-05T15:16:00+0000'],
            ['2123-11-05 15:16', '2123-11-05T15:16:00+0000'],
            ['2123-11-05 15:16+0500', '2123-11-05T15:16:00+0500'],
            ['2123-11-05 15:16-0500', '2123-11-05T15:16:00-0500'],
            ['2123-11-05T15:16-0500', '2123-11-05T15:16:00-0500'],
            ['2123-11-05 15+0500', 'Invalid date value "2123-11-05 15+0500"'],
            ['2123-11-05 15:16:17.123456', '2123-11-05T15:16:17.123456+0000'],
            ['2123-11-05T15:16:17.123456', '2123-11-05T15:16:17.123456+0000'],
            ['2123-11-05T15:16:17.123456+10:32', '2123-11-05T15:16:17.123456+1032'],
            ['2123-11-05T15:16:17.123456+1032', '2123-11-05T15:16:17.123456+1032'],
            ['2123-11-05T15:16:17.123456+1032', '2123-11-05T15:16:17.123456+1032'],
            ['2015/01/01', 'Invalid date value "2015/01/01"'],
            ['2015/01/01 12:00', 'Invalid date value "2015/01/01 12:00"'],
            ['2015/01/01 12:00+0500', 'Invalid date value "2015/01/01 12:00+0500"'],
        ];
    }
}
