<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * `@similar = "<asset id>"` ranks the assets having an embedding by their
 * similarity to the given asset.
 */
class AssetSimilarSearchTest extends AbstractSearchTestCase
{
    private const int DIMS = 512;

    public function testSimilarCondition(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $create = fn (string $name, ?array $vector): Asset => $this->createEmbeddedAsset($workspace, $name, $vector);
        $reference = $create('Reference', self::vector([0 => 1.0]));
        $create('Close', self::vector([0 => 1.0, 1 => 0.2]));
        $create('Far', self::vector([1 => 1.0]));
        $notEmbedded = $create('Not embedded', null);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $this->assertSame(['Close', 'Far'], $this->search(sprintf('@similar = "%s"', $reference->getId())));
        $this->assertSame([], $this->search(sprintf('@similar = "%s"', $notEmbedded->getId())));
    }

    private function createEmbeddedAsset(Workspace $workspace, string $name, ?array $vector): Asset
    {
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => true,
            'no_flush' => true,
        ]);
        if (null !== $vector) {
            $embedding = new AssetEmbedding();
            $embedding->setAsset($asset);
            $embedding->setVector($vector);
            $embedding->setModel('test');
            self::getEntityManager()->persist($embedding);
        }
        self::getEntityManager()->flush();

        return $asset;
    }

    /**
     * @param array<int, float> $components
     *
     * @return float[]
     */
    private static function vector(array $components): array
    {
        $vector = array_fill(0, self::DIMS, 0.0);
        foreach ($components as $i => $value) {
            $vector[$i] = $value;
        }

        return $vector;
    }

    /**
     * @return string[] names of the results, in their order
     */
    private function search(string $condition): array
    {
        $response = self::createClient()->request('GET', '/assets', [
            'query' => ['conditions' => [$condition]],
        ]);
        $data = $this->getDataFromResponse($response, 200)['member'];

        return array_map(fn (array $asset): ?string => $asset['name'] ?? null, $data);
    }
}
