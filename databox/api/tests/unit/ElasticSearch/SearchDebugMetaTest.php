<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\SearchDebugMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SearchDebugMetaTest extends TestCase
{
    #[DataProvider('provideCases')]
    public function testTheQueryIsOnlyExposedInDebugModeOrToTech(bool $debug, bool $tech, bool $expected): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => $tech && JwtUser::ROLE_TECH === $attribute);

        $searchDebugMeta = new SearchDebugMeta($authorizationChecker, $debug);
        $response = new ApiMetaWrapperOutput(new \ArrayIterator([]));
        $searchDebugMeta->apply($response, ['query' => ['term' => ['client' => 'ACME']]], 0.012);

        $this->assertSame($expected, $searchDebugMeta->isAllowed());
        if ($expected) {
            $this->assertSame([
                SearchDebugMeta::META_KEY => [
                    'query' => ['query' => ['term' => ['client' => 'ACME']]],
                    'time' => 0.012,
                ],
            ], $response->getMeta());
        } else {
            $this->assertSame([], $response->getMeta());
        }
    }

    public static function provideCases(): iterable
    {
        yield 'standard user' => [false, false, false];
        yield 'tech user' => [false, true, true];
        yield 'debug mode' => [true, false, true];
        yield 'tech user in debug mode' => [true, true, true];
    }
}
