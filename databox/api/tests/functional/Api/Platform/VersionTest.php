<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /_health/version: public, the version of the image (PHRASEA_VERSION),
 * also served as the version of the OpenAPI documentation.
 */
final class VersionTest extends AbstractDataboxTestCase
{
    public function testTheVersionIsPublic(): void
    {
        $response = static::createClient()->request('GET', '/_health/version');

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('no-store', $response->getHeaders()['cache-control'][0]);

        $data = $response->toArray();
        $this->assertSame(['version', 'commit'], array_keys($data));
        // Read at runtime from the environment of the image, "dev" without it
        $version = getenv('PHRASEA_VERSION');
        $this->assertSame(false !== $version ? $version : 'dev', $data['version']);
        $this->assertSame(getenv('PHRASEA_COMMIT') ?: null, $data['commit']);
    }

    public function testTheOpenApiDocumentationHasTheSameVersion(): void
    {
        $version = static::createClient()->request('GET', '/_health/version')->toArray()['version'];

        $docs = static::createClient()->request('GET', '/docs.jsonopenapi', [
            'headers' => ['Accept' => 'application/vnd.openapi+json'],
        ])->toArray();

        $this->assertSame($version, $docs['info']['version']);
    }
}
