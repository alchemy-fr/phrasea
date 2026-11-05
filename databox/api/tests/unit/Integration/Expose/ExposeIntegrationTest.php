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

        yield 'bare ID of the former synchronizations' => [$id, ['id' => $id, 'ignoreRoi' => false]];
        yield 'regions of interest applied' => [json_encode(['id' => $id, 'ignoreRoi' => false]), ['id' => $id, 'ignoreRoi' => false]];
        yield 'whole files' => [json_encode(['id' => $id, 'ignoreRoi' => true]), ['id' => $id, 'ignoreRoi' => true]];
        yield 'option missing' => [json_encode(['id' => $id]), ['id' => $id, 'ignoreRoi' => false]];
    }
}
