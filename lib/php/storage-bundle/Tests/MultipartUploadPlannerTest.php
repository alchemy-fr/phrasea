<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MultipartUploadPlannerTest extends TestCase
{
    private const int MB = 1024 * 1024;

    private function createPlanner(): MultipartUploadPlanner
    {
        return new MultipartUploadPlanner(
            minChunkSize: 20 * self::MB,
            maxChunkSize: 100 * self::MB,
            maxPartNumber: 10,
            maxObjectSize: 1000 * self::MB,
        );
    }

    #[DataProvider('chunkSizeProvider')]
    public function testResolveChunkSize(int $size, int $expectedChunkSize, int $expectedPartCount): void
    {
        $planner = $this->createPlanner();

        $chunkSize = $planner->resolveChunkSize($size);

        $this->assertSame($expectedChunkSize, $chunkSize);
        $this->assertSame($expectedPartCount, $planner->getPartCount($size, $chunkSize));
    }

    public static function chunkSizeProvider(): array
    {
        return [
            'empty file still has one part' => [0, 20 * self::MB, 1],
            'smaller than a chunk' => [self::MB, 20 * self::MB, 1],
            'exact multiple' => [40 * self::MB, 20 * self::MB, 2],
            'last part is shorter' => [41 * self::MB, 20 * self::MB, 3],
            'fits in max parts with min chunk' => [200 * self::MB, 20 * self::MB, 10],
            'chunk grows to respect max part number' => [200 * self::MB + 1, 20 * self::MB + 1, 10],
            'largest allowed file' => [1000 * self::MB, 100 * self::MB, 10],
        ];
    }

    public function testFileAboveMaxObjectSizeIsRejected(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/exceeds the maximum allowed size/');

        $this->createPlanner()->resolveChunkSize(1000 * self::MB + 1);
    }

    public function testFileNeedingTooLargePartsIsRejected(): void
    {
        $planner = new MultipartUploadPlanner(
            minChunkSize: 20 * self::MB,
            maxChunkSize: 50 * self::MB,
            maxPartNumber: 10,
            maxObjectSize: 1000 * self::MB,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/above the maximum of/');

        $planner->resolveChunkSize(600 * self::MB);
    }

    public function testNegativeSizeIsRejected(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->createPlanner()->resolveChunkSize(-1);
    }

    public function testInvalidLimitsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MultipartUploadPlanner(minChunkSize: 10, maxChunkSize: 5, maxPartNumber: 10, maxObjectSize: 100);
    }
}
