<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Expose;

use App\Entity\Integration\IntegrationData;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExposeIntegrationTest extends TestCase
{
    #[DataProvider('providePublicationData')]
    public function testReadPublicationData(string $value, array $expected): void
    {
        $data = new IntegrationData();
        $data->setValue($value);

        $this->assertSame($expected, ExposeIntegration::readPublicationData($data));
    }

    public static function providePublicationData(): iterable
    {
        $id = '2c1d7d1e-3c4b-4b8a-9d55-0d6f8b1c2a3e';
        $none = ['skipped' => [], 'skippedTotal' => 0];

        yield 'bare ID of the former synchronizations' => [$id, ['id' => $id, 'ignoreRoi' => false, 'title' => null] + $none];
        yield 'regions of interest applied' => [json_encode(['id' => $id, 'ignoreRoi' => false]), ['id' => $id, 'ignoreRoi' => false, 'title' => null] + $none];
        yield 'whole files' => [json_encode(['id' => $id, 'ignoreRoi' => true]), ['id' => $id, 'ignoreRoi' => true, 'title' => null] + $none];
        yield 'option missing' => [json_encode(['id' => $id]), ['id' => $id, 'ignoreRoi' => false, 'title' => null] + $none];
        yield 'with its title' => [json_encode(['id' => $id, 'ignoreRoi' => false, 'title' => 'Press kit']), ['id' => $id, 'ignoreRoi' => false, 'title' => 'Press kit'] + $none];

        $skipped = ['assetId' => 'a1', 'basketAssetId' => 'b1', 'title' => 'Report', 'reason' => 'Not allowed'];
        yield 'with refused assets' => [
            json_encode(['id' => $id, 'ignoreRoi' => false, 'title' => null, 'skipped' => [$skipped, 'invalid'], 'skippedTotal' => 60]),
            ['id' => $id, 'ignoreRoi' => false, 'title' => null, 'skipped' => [$skipped], 'skippedTotal' => 60],
        ];
        yield 'refused assets without their count' => [
            json_encode(['id' => $id, 'skipped' => [$skipped]]),
            ['id' => $id, 'ignoreRoi' => false, 'title' => null, 'skipped' => [$skipped], 'skippedTotal' => 1],
        ];
    }

    public function testWritePublicationData(): void
    {
        $publication = [
            'id' => '2c1d7d1e-3c4b-4b8a-9d55-0d6f8b1c2a3e',
            'ignoreRoi' => true,
            'title' => 'Press kit',
            'skipped' => [['assetId' => 'a1', 'basketAssetId' => 'b1', 'title' => 'Report', 'reason' => 'Not allowed']],
            'skippedTotal' => 3,
        ];
        $data = new IntegrationData();

        ExposeIntegration::writePublicationData($data, $publication);

        $this->assertSame($publication, ExposeIntegration::readPublicationData($data));
    }
}
