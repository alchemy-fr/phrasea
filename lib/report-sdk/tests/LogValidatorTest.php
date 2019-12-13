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
            [['appName' => 'foo']],
            [['appName' => 'foo', 'appId' => 'app-123']],
            [['appName' => 'foo', 'appId' => 'app-123', 'action' => 'invalid-action-format']],
            [['appName' => 'foo', 'appId' => 'app-123', 'action' => '09']],
        ];
    }
}
