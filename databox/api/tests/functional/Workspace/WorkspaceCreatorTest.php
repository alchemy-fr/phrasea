<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workspace;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use App\Entity\Core\RenditionDefinition;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WorkspaceCreatorTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    public function testDefaultRenditionDefinitions(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-defaults');
        $definitions = $defaults->renditionDefinitions;

        $this->assertSame(
            ['source', 'main', 'preview', 'thumbnail', 'animatedThumbnail', 'storyContactSheet'],
            array_keys($definitions),
        );
        $this->assertSame(
            ['Source', 'Main', 'Preview', 'Thumbnail', 'AnimatedThumbnail', 'StoryContactSheet'],
            array_values(array_map(fn (RenditionDefinition $d): string => $d->getName(), $definitions)),
        );
        foreach ($definitions as $key => $definition) {
            $this->assertSame($key, $definition->getKey());
        }
        $this->assertCount(6, self::getEntityManager()->getRepository(RenditionDefinition::class)->findBy(['workspace' => $workspace->getId()]));
    }

    public function testSourceIsTheAssetFileAsIs(): void
    {
        [, $defaults] = $this->createWorkspaceWithDefaults('ws-source');
        $source = $defaults->renditionDefinitions['source'];

        $this->assertSame(RenditionDefinition::BUILD_MODE_PICK_SOURCE, $source->getBuildMode());
        $this->assertNull($source->getParent());
        $this->assertSame(AssetTypeEnum::Asset, $source->getTarget());
        $this->assertFalse($source->isSubstitutable());
        $this->assertTrue($source->isDownload());
        $this->assertFalse($source->isBuildFirst());
        $this->assertFalse($source->isUseAsMain());
        $this->assertFalse($source->isUseAsPreview());
        $this->assertFalse($source->isUseAsThumbnail());
        $this->assertFalse($source->isUseAsAnimatedThumbnail());
        $this->assertSame($defaults->renditionPolicy, $source->getPolicy());
    }

    public function testMainIsBuiltForTheWebFromEveryFileFamily(): void
    {
        [, $defaults] = $this->createWorkspaceWithDefaults('ws-main');
        $main = $defaults->renditionDefinitions['main'];

        $this->assertTrue($main->isUseAsMain());
        $this->assertNull($main->getParent());
        $this->assertSame(RenditionDefinition::BUILD_MODE_CUSTOM, $main->getBuildMode());

        $config = new YamlLoader()->parse($main->getDefinition());
        foreach (FamilyEnum::cases() as $family) {
            $this->assertNotNull($config->getFamily($family), sprintf('Main has no build for the "%s" family: its job would be skipped', $family->value));
        }
    }

    public function testPreviewIsBuiltFromMain(): void
    {
        [, $defaults] = $this->createWorkspaceWithDefaults('ws-preview');
        $definitions = $defaults->renditionDefinitions;

        $this->assertSame($definitions['main'], $definitions['preview']->getParent());
        $this->assertSame($definitions['preview'], $definitions['thumbnail']->getParent());
        $this->assertSame($definitions['preview'], $definitions['animatedThumbnail']->getParent());
    }

    public function testEveryBuildDefinitionIsValid(): void
    {
        [, $defaults] = $this->createWorkspaceWithDefaults('ws-valid');
        $validator = self::getService(ValidatorInterface::class);

        foreach ($defaults->renditionDefinitions as $key => $definition) {
            $violations = $validator->validateProperty($definition, 'definition');
            $this->assertCount(0, $violations, sprintf('%s: %s', $key, (string) $violations));
        }
    }
}
