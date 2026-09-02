<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Notification;

use Alchemy\NotifierBundle\Notification\NotificationUrlGenerator;
use PHPUnit\Framework\TestCase;

final class NotificationUrlGeneratorTest extends TestCase
{
    public function testWrapsRelativeUri(): void
    {
        $generator = new NotificationUrlGenerator('https://databox.example.com');

        self::assertSame(
            'https://databox.example.com/notification-uri?uri=%2Fassets%2F42',
            $generator->generate('/assets/42')
        );
    }

    public function testKeepsFragmentInEncodedUri(): void
    {
        $generator = new NotificationUrlGenerator('https://databox.example.com/');

        self::assertSame(
            'https://databox.example.com/notification-uri?uri=%2Fassets%2F42%23discussion-7',
            $generator->generate('/assets/42#discussion-7')
        );
    }

    public function testCustomPath(): void
    {
        $generator = new NotificationUrlGenerator('https://databox.example.com', 'go');

        self::assertSame(
            'https://databox.example.com/go?uri=%2Fassets%2F42',
            $generator->generate('/assets/42')
        );
    }

    public function testAbsoluteUriIsLeftUntouched(): void
    {
        $generator = new NotificationUrlGenerator('https://databox.example.com');

        self::assertSame('https://other.example.com/foo', $generator->generate('https://other.example.com/foo'));
    }

    public function testUriIsLeftUntouchedWithoutClientUrl(): void
    {
        $generator = new NotificationUrlGenerator();

        self::assertSame('/assets/42', $generator->generate('/assets/42'));
    }

    public function testEmptyUriReturnsNull(): void
    {
        $generator = new NotificationUrlGenerator('https://databox.example.com');

        self::assertNull($generator->generate(null));
        self::assertNull($generator->generate(''));
    }
}
