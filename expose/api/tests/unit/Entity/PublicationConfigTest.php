<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PublicationConfig;
use PHPUnit\Framework\TestCase;

class PublicationConfigTest extends TestCase
{
    public function testPasswordIsHashedAndNeverRead(): void
    {
        $config = new PublicationConfig();
        $config->setPassword('s3cr3t');

        $this->assertSame(PublicationConfig::SECURITY_METHOD_PASSWORD, $config->getSecurityMethod());
        $this->assertTrue($config->getHasPassword());
        $this->assertSame([], $config->getSecurityOptions());
        $this->assertTrue($config->isPasswordValid('s3cr3t'));
        $this->assertFalse($config->isPasswordValid('other'));
    }

    public function testEmptyPasswordKeepsTheCurrentOne(): void
    {
        $config = new PublicationConfig();
        $config->setPassword('s3cr3t');

        $config->setPassword('');
        $config->setPassword(null);
        $config->setSecurityOptions([]);
        $config->setSecurityOptions(['password' => '']);
        $config->setSecurityMethod(PublicationConfig::SECURITY_METHOD_PASSWORD);
        $this->assertTrue($config->isPasswordValid('s3cr3t'));

        $config->setSecurityOptions(['password' => 'new_one']);
        $this->assertFalse($config->isPasswordValid('s3cr3t'));
        $this->assertTrue($config->isPasswordValid('new_one'));
    }

    public function testAnotherSecurityMethodDropsThePassword(): void
    {
        foreach ([null, '', PublicationConfig::SECURITY_METHOD_AUTHENTICATION] as $method) {
            $config = new PublicationConfig();
            $config->setPassword('s3cr3t');
            $config->setSecurityMethod($method);

            $this->assertFalse($config->getHasPassword());
            $this->assertFalse($config->hasSecurityOptions());
            $this->assertFalse($config->isPasswordValid('s3cr3t'));
        }
    }
}
