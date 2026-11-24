<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Util;

use Alchemy\CoreBundle\Util\UuidUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UuidUtilTest extends TestCase
{
    private const string VALID = '0193a8b2-6c1d-7e2f-9a3b-4c5d6e7f8091';

    public static function invalidIdProvider(): array
    {
        return [
            'not an UUID' => ['not-a-uuid'],
            'empty string' => [''],
            'null' => [null],
            'integer' => [42],
            'list' => [[self::VALID]],
            'truncated' => [substr(self::VALID, 0, -1)],
            'trailing character' => [self::VALID.'0'],
            'URN form' => ['urn:uuid:'.self::VALID],
            'braces' => ['{'.self::VALID.'}'],
        ];
    }

    public function testValidId(): void
    {
        $this->assertTrue(UuidUtil::isValid(self::VALID));
        $this->assertTrue(UuidUtil::isValid(strtoupper(self::VALID)));
        $this->assertSame(self::VALID, UuidUtil::assertValid(self::VALID, 'id'));
        $this->assertSame(self::VALID, UuidUtil::assertValidOrNotFound(self::VALID, 'id'));
    }

    #[DataProvider('invalidIdProvider')]
    public function testIsValidRejects(mixed $id): void
    {
        $this->assertFalse(UuidUtil::isValid($id));
    }

    #[DataProvider('invalidIdProvider')]
    public function testAssertValidThrowsBadRequest(mixed $id): void
    {
        try {
            UuidUtil::assertValid($id, 'workspaceId');
            $this->fail('A BadRequestHttpException was expected');
        } catch (BadRequestHttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Invalid "workspaceId"', $e->getMessage());
        }
    }

    #[DataProvider('invalidIdProvider')]
    public function testAssertValidOrNotFoundThrowsNotFound(mixed $id): void
    {
        try {
            UuidUtil::assertValidOrNotFound($id, 'id');
            $this->fail('A NotFoundHttpException was expected');
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Invalid "id"', $e->getMessage());
        }
    }
}
