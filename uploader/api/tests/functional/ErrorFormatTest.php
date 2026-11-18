<?php

declare(strict_types=1);

namespace App\Tests\Functional;

class ErrorFormatTest extends AbstractUploaderTestCase
{
    public function testUnknownRouteIsNotFoundWhateverTheAcceptedFormat(): void
    {
        foreach (['application/vnd.api+json', 'application/hal+json', 'application/xml'] as $accept) {
            $response = static::createClient()->request('GET', '/jsonapi/node/page', [
                'headers' => ['Accept' => $accept],
            ]);
            $this->assertSame(404, $response->getStatusCode(), $accept);
        }
    }
}
