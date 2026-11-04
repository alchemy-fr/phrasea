<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * MultipleAssetOutput is the output DTO of POST /assets/multiple. Because
 * src/Api/Model/Output is an API Platform mapping path and the class carries
 * a bare #[ApiResource], API Platform also registers default operations on
 * /multiple-asset-outputs, without identifier, provider nor processor.
 * Nothing should be served there.
 */
final class MultipleAssetOutputTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    public function testGetIsNotFound(): void
    {
        $this->jsonRequest('GET', '/multiple-asset-outputs', KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', '/multiple-asset-outputs', null);
        $this->assertResponseStatusCodeSame(404);

        // No identifier: there is no item route
        $this->jsonRequest('GET', '/multiple-asset-outputs/f1b4b4a8-0000-4000-8000-000000000000', KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('getWriteMethods')]
    public function testWriteMethodsAreNotServed(string $method, ?array $body): void
    {
        $this->markTestIncomplete('BUG: MultipleAssetOutput is an output DTO exposed by a bare #[ApiResource] (src/Api/Model/Output/MultipleAssetOutput.php:11): POST/PUT/PATCH/DELETE /multiple-asset-outputs answer 500 ("No input transformer found" / unresolvable $data) instead of 404/405.');

        $response = $this->jsonRequest($method, '/multiple-asset-outputs', KeycloakClientTestMock::ADMIN_UID, $body);
        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public static function getWriteMethods(): array
    {
        return [
            'POST' => ['POST', ['assets' => []]],
            'PUT' => ['PUT', ['assets' => []]],
            'PATCH' => ['PATCH', ['assets' => []]],
            'DELETE' => ['DELETE', null],
        ];
    }
}
