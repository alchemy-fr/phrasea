<?php

declare(strict_types=1);

namespace App\Tests\Functional;

class ClientLogTest extends AbstractExposeTestCase
{
    public function testOnlyVisibleContentIsLogged(): void
    {
        $visible = $this->createPublication();
        $visibleAssetId = $this->createAsset($visible);
        $hidden = $this->createPublication(['enabled' => false]);
        $hiddenAssetId = $this->createAsset($hidden);
        $protected = $this->createPublication(['password' => 's3cr3t']);
        $protectedAssetId = $this->createAsset($protected);
        $this->clearEmBeforeApiCall();

        $this->assertLogStatus(200, '/logs/publication-view/'.$visible->getId());
        $this->assertLogStatus(200, '/logs/asset-view/'.$visibleAssetId);
        $this->assertLogStatus(403, '/logs/publication-view/'.$hidden->getId());
        $this->assertLogStatus(403, '/logs/asset-view/'.$hiddenAssetId);
        $this->assertLogStatus(404, '/logs/publication-view/2ef2b0c9-4f4d-4ef8-8cc6-1b0e6c2a4a1d');

        // The password screen of a protected publication is viewed, not its assets.
        $this->assertLogStatus(200, '/logs/publication-view/'.$protected->getId());
        $this->assertLogStatus(403, '/logs/asset-view/'.$protectedAssetId);
        $this->assertLogStatus(200, '/logs/asset-view/'.$protectedAssetId, [
            'HTTP_X-Passwords' => base64_encode(json_encode([$protected->getId() => 's3cr3t'], JSON_THROW_ON_ERROR)),
        ]);
    }

    public function testLogsAreLimitedByClient(): void
    {
        $id = $this->createPublication()->getId();
        $this->clearEmBeforeApiCall();

        for ($i = 1; $i <= 60; ++$i) {
            $this->assertLogStatus(200, '/logs/publication-view/'.$id);
        }
        $this->assertLogStatus(429, '/logs/publication-view/'.$id);
        $this->assertLogStatus(200, '/logs/publication-view/'.$id, ['REMOTE_ADDR' => '10.0.0.1']);
    }

    private function assertLogStatus(int $expectedStatus, string $uri, array $server = []): void
    {
        $response = $this->request(null, 'POST', $uri, [], [], $server);
        $this->assertSame($expectedStatus, $response->getStatusCode(), $uri.': '.substr((string) $response->getContent(), 0, 300));
    }
}
