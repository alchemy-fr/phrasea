<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK\Tests;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Alchemy\ReportSDK\LogValidator;
use PHPUnit\Framework\TestCase;

class LogValidatorTest extends TestCase
{
    /**
     * @dataProvider invalidLogData
     */
    public function testInvalidLog(array $data): void
    {
        $validator = new LogValidator();

        $this->expectException(InvalidLogException::class);
        $validator->validate($data);
    }

    public function invalidLogData(): array
    {
        return [
            [[]],
            [['app' => 'foo']],
            [['app' => 'foo', 'action' => 'invalid-action-format']],
            [['app' => 'foo', 'action' => '09']],
            [['app' => 'foo', 'action' => 'unsupported_action']],
        ];
    }
}
