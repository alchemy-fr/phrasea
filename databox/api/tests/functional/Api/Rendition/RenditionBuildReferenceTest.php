<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /rendition-build-reference: the (public) documentation of the build
 * definition format and of each transformation module.
 */
final class RenditionBuildReferenceTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    public function testReferenceIsPubliclyAvailable(): void
    {
        $response = $this->jsonRequest('GET', '/rendition-build-reference', null);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/rendition-build-reference',
            '@type' => 'rendition-build-reference',
            'id' => 'rendition-build-reference',
        ]);

        $data = $response->toArray();
        $this->assertStringContainsString('transformations', $data['reference']);
        $this->assertStringContainsString('image', $data['reference']);

        $modules = array_column($data['references'], null, 'name');
        foreach (['imagine', 'ffmpeg', 'void'] as $name) {
            $this->assertArrayHasKey($name, $modules, sprintf('Module "%s" is documented', $name));
            $this->assertArrayHasKey('description', $modules[$name]);
            $this->assertIsString($modules[$name]['reference']);
        }
        $this->assertNotEmpty($modules['imagine']['reference']);
    }

    public function testReferenceIsTheSameForAuthenticatedUsers(): void
    {
        $anonymous = $this->jsonRequest('GET', '/rendition-build-reference', null)->toArray();
        $authenticated = $this->jsonRequest('GET', '/rendition-build-reference', KeycloakClientTestMock::USER_UID)->toArray();

        $this->assertSame($anonymous['reference'], $authenticated['reference']);
        $this->assertSame(array_column($anonymous['references'], 'name'), array_column($authenticated['references'], 'name'));
    }

    public function testOnlyGetIsExposed(): void
    {
        $this->jsonRequest('POST', '/rendition-build-reference', KeycloakClientTestMock::ADMIN_UID, ['reference' => 'x']);
        $this->assertResponseStatusCodeSame(405);
    }
}
