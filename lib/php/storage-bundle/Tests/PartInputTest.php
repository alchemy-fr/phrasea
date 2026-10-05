<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Api\Dto\PartInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PartInputTest extends TestCase
{
    public function testValidPart(): void
    {
        $part = PartInput::fromArray(['PartNumber' => '2', 'ETag' => ' "abc" ']);

        $this->assertSame(2, $part->PartNumber);
        $this->assertSame('"abc"', $part->ETag);
    }

    /**
     * @dataProvider invalidPartProvider
     */
    public function testInvalidPartIsRejected(array $data): void
    {
        $this->expectException(BadRequestHttpException::class);

        PartInput::fromArray($data);
    }

    public function invalidPartProvider(): array
    {
        return [
            'missing ETag' => [['PartNumber' => 1]],
            'null ETag' => [['PartNumber' => 1, 'ETag' => null]],
            'blank ETag' => [['PartNumber' => 1, 'ETag' => '  ']],
            'missing PartNumber' => [['ETag' => 'abc']],
            'zero PartNumber' => [['PartNumber' => 0, 'ETag' => 'abc']],
            'string PartNumber' => [['PartNumber' => 'one', 'ETag' => 'abc']],
        ];
    }
}
