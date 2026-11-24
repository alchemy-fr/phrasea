<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validation;

use App\Validation\SecureTargetUrl;
use App\Validation\SecureTargetUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<SecureTargetUrlValidator>
 */
class SecureTargetUrlValidatorTest extends ConstraintValidatorTestCase
{
    private string $environment = 'prod';

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new SecureTargetUrlValidator($this->environment);
    }

    public static function acceptedProvider(): iterable
    {
        yield 'none' => [null];
        yield 'https' => ['https://phraseanet.example.com/api/v1/upload/enqueue/'];
        yield 'https, upper case' => ['HTTPS://phraseanet.example.com/'];
        yield 'http to a host of the stack' => ['http://databox-api/incoming-uploads'];
        yield 'http to a host of the stack, with a port' => ['http://databox-api:8080/incoming-uploads'];
        yield 'another scheme, for the Url constraint' => ['ftp://example.com/'];
    }

    #[DataProvider('acceptedProvider')]
    public function testAccepted(?string $url): void
    {
        $this->validator->validate($url, new SecureTargetUrl());

        $this->assertNoViolation();
    }

    public static function refusedProvider(): iterable
    {
        yield 'http' => ['http://phraseanet.example.com/api/v1/upload/enqueue/'];
        yield 'http, upper case' => ['HTTP://phraseanet.example.com/'];
        yield 'http to an IP address' => ['http://10.0.0.1/enqueue'];
    }

    #[DataProvider('refusedProvider')]
    public function testRefused(string $url): void
    {
        $constraint = new SecureTargetUrl();
        $this->validator->validate($url, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testHttpIsAcceptedInTheDevEnvironment(): void
    {
        $this->environment = 'dev';
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('http://phraseanet.example.com/', new SecureTargetUrl());

        $this->assertNoViolation();
    }
}
