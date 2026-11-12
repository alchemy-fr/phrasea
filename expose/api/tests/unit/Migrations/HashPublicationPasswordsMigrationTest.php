<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use DoctrineMigrations\Version20261111093412;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../migrations/Version20261111093412.php';

class HashPublicationPasswordsMigrationTest extends TestCase
{
    public function testPlainPasswordIsHashed(): void
    {
        $options = Version20261111093412::hashPassword('{"password":"$3cr3t!","foo":"bar"}');
        $this->assertNotNull($options);

        $decoded = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('bar', $decoded['foo']);
        $this->assertNotSame('$3cr3t!', $decoded['password']);
        $this->assertTrue(password_verify('$3cr3t!', $decoded['password']));
    }

    public function testNothingToHash(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        foreach ([
            null,
            '',
            '[]',
            '{}',
            '{"password":""}',
            '{"password":null}',
            json_encode(['password' => $hash], JSON_THROW_ON_ERROR),
            json_encode(['password' => '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG'], JSON_THROW_ON_ERROR),
        ] as $options) {
            $this->assertNull(Version20261111093412::hashPassword($options), (string) $options);
        }
    }
}
