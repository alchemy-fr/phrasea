<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Elasticsearch\AssetSearch;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Security\AttributeFilterManager;
use App\Service\Asset\VisibleAssetResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class VisibleAssetResolverTest extends TestCase
{
    private AttributeFilterManager&MockObject $attributeFilterManager;
    private AssetSearch&MockObject $assetSearch;
    /** @var string[] IDs of the assets the voter denies */
    private array $denied = [];

    private function createResolver(array $rules): VisibleAssetResolver
    {
        $this->attributeFilterManager = $this->createMock(AttributeFilterManager::class);
        $this->attributeFilterManager->method('getUserRules')->willReturn($rules);
        $this->assetSearch = $this->createMock(AssetSearch::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new JwtUser('JWT', 'u1', 'user', [], ['g1']));
        $security->method('isGranted')->willReturnCallback(
            fn (mixed $attribute, mixed $subject): bool => !in_array($subject->getId(), $this->denied, true),
        );

        $resolver = new VisibleAssetResolver($this->attributeFilterManager, $this->assetSearch);
        $resolver->setSecurity($security);

        return $resolver;
    }

    private static function createAsset(Workspace $workspace): Asset
    {
        $asset = new Asset();
        $asset->setWorkspace($workspace);

        return $asset;
    }

    public function testWithoutRuleTheVoterAloneDecides(): void
    {
        $resolver = $this->createResolver([]);
        $this->assetSearch->expects($this->never())->method('getVisibleIds');

        $workspace = new Workspace();
        $readable = self::createAsset($workspace);
        $denied = self::createAsset($workspace);
        $this->denied = [$denied->getId()];

        $this->assertSame([$readable], $resolver->filterVisible([$denied, $readable]));
        $this->assertFalse($resolver->isVisible($denied));
    }

    public function testUnderARuleOnlyTheAssetsOfItsWorkspaceAreSearchedAtOnce(): void
    {
        $ruled = new Workspace();
        $free = new Workspace();
        $resolver = $this->createResolver([$ruled->getId() => ['@tag = "x"']]);

        $shown = self::createAsset($ruled);
        $hidden = self::createAsset($ruled);
        $denied = self::createAsset($ruled);
        $other = self::createAsset($free);
        $this->denied = [$denied->getId()];

        $this->assetSearch->expects($this->once())
            ->method('getVisibleIds')
            ->with('u1', ['g1'], [$hidden->getId(), $shown->getId()])
            ->willReturn([$shown->getId()]);

        $this->assertSame([$other, $shown], $resolver->filterVisible([$hidden, $other, $denied, $shown]));

        // Memoized: no new search
        $this->assertTrue($resolver->isVisible($shown));
        $this->assertFalse($resolver->isVisible($hidden));
    }

    public function testResetForgetsTheDecisions(): void
    {
        $workspace = new Workspace();
        $resolver = $this->createResolver([$workspace->getId() => ['@tag = "x"']]);
        $asset = self::createAsset($workspace);

        $this->assetSearch->expects($this->exactly(2))
            ->method('getVisibleIds')
            ->willReturn([$asset->getId()]);

        $this->assertTrue($resolver->isVisible($asset));
        $this->assertTrue($resolver->isVisible($asset));
        $resolver->reset();
        $this->assertTrue($resolver->isVisible($asset));
    }
}
