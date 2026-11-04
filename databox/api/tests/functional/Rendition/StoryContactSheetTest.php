<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\Workflow\Model\Workflow;
use App\Consumer\Handler\Asset\BuildStoryContactSheet;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\IntegrationManager;
use App\Model\AssetTypeEnum;
use App\Repository\Core\CollectionAssetRepository;
use App\Service\Asset\AssetManager;
use App\Service\Asset\RenditionBuild\Exception\RenditionBuildException;
use App\Service\Asset\RenditionBuilder;
use App\Service\Asset\RenditionBuildHashManager;
use App\Service\Asset\StoryContactSheetInputResolver;
use App\Service\Asset\StoryContactSheetScheduler;
use App\Service\Storage\RenditionManager;
use App\Service\Vector\AssetEmbeddingManager;
use App\Service\Workspace\WorkspaceDefaults;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The "generate" rendition of a story (default "StoryContactSheet" definition):
 * a grid of the already built thumbnails (or previews) of its items.
 */
final class StoryContactSheetTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const array COLORS = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];

    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testSheetOfAStoryWithoutCoverIsBuiltFromTheItemThumbnails(): void
    {
        $transport = $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-build');
        $story = $this->createStory($workspace);
        foreach (array_slice(self::COLORS, 0, 5) as $color) {
            $this->createItem($story, $defaults, $color);
        }

        $this->assertContains($story->getId(), $this->getScheduledStoryIds($transport), 'Adding items schedules the sheet');

        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        self::getService(RenditionBuilder::class)->buildRendition($sheetDefinition, $story);

        $rendition = $this->getRendition($story, $sheetDefinition);
        $this->assertNotNull($rendition);
        $this->assertSame('image/webp', $rendition->getFile()->getType());
        $this->assertFalse($rendition->getProjection());

        $image = $this->readImage($rendition->getFile());
        $this->assertSame(400, $image->getImageWidth());
        $this->assertSame(400, $image->getImageHeight());
        $this->assertColorNear('#ff0000', $image, 100, 100);
        $this->assertColorNear('#00ff00', $image, 300, 100);
        $this->assertColorNear('#0000ff', $image, 100, 300);
        $this->assertColorNear('#ffff00', $image, 300, 300);
    }

    public function testUnchangedInputsKeepTheSheet(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-unchanged');
        $story = $this->createStory($workspace);
        $this->createItem($story, $defaults, '#ff0000');
        $this->createItem($story, $defaults, '#00ff00');

        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        $builder = self::getService(RenditionBuilder::class);
        $builder->buildRendition($sheetDefinition, $story);
        $first = $this->getRendition($story, $sheetDefinition);
        $hash = $first->getBuildHash();
        $fileId = $first->getFile()->getId();

        $builder->buildRendition($sheetDefinition, $story);
        $second = $this->getRendition($story, $sheetDefinition);
        $this->assertSame($hash, $second->getBuildHash());
        $this->assertSame($fileId, $second->getFile()->getId(), 'No new file');

        $this->assertFalse(self::getService(RenditionBuildHashManager::class)->isRenditionDirty($second));
    }

    public function testOrderAndRemovalChangeTheSheet(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-order');
        $story = $this->createStory($workspace);
        $first = $this->createItem($story, $defaults, '#ff0000');
        $second = $this->createItem($story, $defaults, '#00ff00');
        $this->createItem($story, $defaults, '#0000ff');

        $em = self::getEntityManager();
        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        $builder = self::getService(RenditionBuilder::class);
        $builder->buildRendition($sheetDefinition, $story);
        $initialHash = $this->getRendition($story, $sheetDefinition)->getBuildHash();

        $firstRelation = $this->getRelation($story, $first);
        $secondRelation = $this->getRelation($story, $second);
        $position = $firstRelation->getPosition();
        $firstRelation->setPosition($secondRelation->getPosition());
        $secondRelation->setPosition($position);
        $em->flush();

        $builder->buildRendition($sheetDefinition, $story);
        $reorderedHash = $this->getRendition($story, $sheetDefinition)->getBuildHash();
        $this->assertNotSame($initialHash, $reorderedHash);
        $image = $this->readImage($this->getRendition($story, $sheetDefinition)->getFile());
        $this->assertColorNear('#00ff00', $image, 100, 100, 'The moved item comes first');

        $em->remove($this->getRelation($story, $second));
        $em->flush();

        $builder->buildRendition($sheetDefinition, $story);
        $this->assertNotSame($reorderedHash, $this->getRendition($story, $sheetDefinition)->getBuildHash());
    }

    public function testDeletedAndQuarantinedItemsAreLeftOut(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-filter');
        $story = $this->createStory($workspace);
        $deleted = $this->createItem($story, $defaults, '#ff0000');
        $quarantined = $this->createItem($story, $defaults, '#00ff00');
        $kept = $this->createItem($story, $defaults, '#0000ff');

        $deleted->setDeletedAt(new \DateTimeImmutable());
        $quarantined->setStatus(AssetStatusEnum::Quarantined);
        self::getEntityManager()->flush();

        $inputs = self::getService(StoryContactSheetInputResolver::class)->resolve($story, 'thumbnail', 4);
        $this->assertFalse($inputs->pending);
        $this->assertSame([$this->getThumbnailFileId($kept, $defaults)], array_map(fn (File $f): string => $f->getId(), $inputs->files));
    }

    public function testOnlyTheFirstCellsAreRead(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-limit');
        $story = $this->createStory($workspace);
        $items = [];
        foreach (self::COLORS as $color) {
            $items[] = $this->createItem($story, $defaults, $color);
        }

        /** @var CollectionAssetRepository $repository */
        $repository = self::getEntityManager()->getRepository(CollectionAsset::class);
        $this->assertSame(
            array_map(fn (Asset $a): string => $a->getId(), array_slice($items, 0, 4)),
            $repository->findStoryContactSheetItemIds($story->getStoryCollection(), 4),
        );
    }

    public function testNotAStoryIsSkipped(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-not-story');
        $asset = $this->createAsset(['workspace' => $workspace]);

        $builder = self::getService(RenditionBuilder::class);
        $this->assertTrue($builder->isGenerateDefinition($defaults->renditionDefinitions['storyContactSheet']));
        $this->assertFalse($builder->isGenerateDefinition($defaults->renditionDefinitions['thumbnail']));

        try {
            $builder->buildRendition($defaults->renditionDefinitions['storyContactSheet'], $asset);
            $this->fail('A non-story asset has no contact sheet');
        } catch (RenditionBuildException $e) {
            $this->assertTrue($e->shouldSkipJob());
            $this->assertSame('not a story', $e->getMessage());
        }
    }

    public function testSkippedSheetJobDoesNotBlockTheIngest(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-job');
        $renditionIntegration = self::getEntityManager()->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $workspace->getId(),
            'integration' => RenditionIntegration::getName(),
        ]);
        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($renditionIntegration);

        /** @var RenditionIntegration $integration */
        $integration = $config->getIntegration();
        $jobs = iterator_to_array($integration->getWorkflowJobDefinitions($config, new Workflow('test')));

        $this->assertTrue($jobs[$defaults->renditionDefinitions['storyContactSheet']->getId()]->isContinueOnSkip());
        $this->assertFalse($jobs[$defaults->renditionDefinitions['thumbnail']->getId()]->isContinueOnSkip());
        $this->assertNotContains(
            $defaults->renditionDefinitions['storyContactSheet']->getId(),
            self::getService(AssetEmbeddingManager::class)->getFallbackRenditionDefinitionIds($workspace->getId()),
            'The similarity of an asset is not computed from a story sheet',
        );
    }

    public function testBuildWaitsForTheItemRenditions(): void
    {
        $transport = $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-pending');
        $story = $this->createStory($workspace);
        $this->createItem($story, $defaults, '#ff0000');
        // Its source is not even stored: reading it would fail
        $pending = $this->createItem($story, $defaults, null, source: true);
        $this->createItem($story, $defaults, '#0000ff');

        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        $builder = self::getService(RenditionBuilder::class);
        $builder->buildRendition($sheetDefinition, $story);
        $this->assertNull($this->getRendition($story, $sheetDefinition), 'No incomplete sheet');
        $this->assertContains(1, array_map(
            fn (BuildStoryContactSheet $m): int => $m->getAttempt(),
            array_filter($this->getScheduledMessages($transport), fn (BuildStoryContactSheet $m): bool => $m->getStoryAssetId() === $story->getId()),
        ), 'A retry is scheduled');

        $this->createAssetRendition($pending, $defaults->renditionDefinitions['thumbnail'], $this->createImageFile($workspace, '#00ff00'));
        $builder->buildRendition($sheetDefinition, $story, attempt: 1);

        $image = $this->readImage($this->getRendition($story, $sheetDefinition)->getFile());
        $this->assertColorNear('#00ff00', $image, 300, 100, 'Built from the item thumbnail, not its source');
    }

    public function testBuildGivesUpWaitingAfterTheLastAttempt(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-give-up');
        $story = $this->createStory($workspace);
        $this->createItem($story, $defaults, null, source: true);
        $this->createItem($story, $defaults, '#0000ff');

        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        self::getService(RenditionBuilder::class)->buildRendition($sheetDefinition, $story, attempt: StoryContactSheetScheduler::MAX_ATTEMPTS);

        $image = $this->readImage($this->getRendition($story, $sheetDefinition)->getFile());
        $this->assertColorNear('#0000ff', $image, 100, 100, 'Built with the ready items');
    }

    public function testSheetIsRemovedWithoutAnyItemImage(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-empty');
        $story = $this->createStory($workspace);
        $item = $this->createItem($story, $defaults, '#ff0000');

        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        $builder = self::getService(RenditionBuilder::class);
        $builder->buildRendition($sheetDefinition, $story);
        $this->assertNotNull($this->getRendition($story, $sheetDefinition));

        self::getEntityManager()->remove($this->getRelation($story, $item));
        self::getEntityManager()->flush();

        $builder->buildRendition($sheetDefinition, $story);
        $this->assertNull($this->getRendition($story, $sheetDefinition));
    }

    public function testPreviewsCanBeAssembled(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-preview');
        $sheetDefinition = $this->createRenditionDefinition($workspace, $defaults->renditionPolicy, 'Preview sheet', [
            'target' => AssetTypeEnum::Story,
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => <<<'YAML'
generate:
    input: preview
    transformations:
        - module: contact_sheet
          options: {grid: 4, size: 200, gap: 0, format: png}
YAML,
        ]);

        $story = $this->createStory($workspace);
        $item = $this->createAsset(['workspace' => $workspace, 'collectionId' => $story->getStoryCollection()->getId()]);
        $this->createAssetRendition($item, $defaults->renditionDefinitions['preview'], $this->createImageFile($workspace, '#ff00ff'));

        self::getService(RenditionBuilder::class)->buildRendition($sheetDefinition, $story);

        $rendition = $this->getRendition($story, $sheetDefinition);
        $this->assertSame('image/png', $rendition->getFile()->getType());
        $image = $this->readImage($rendition->getFile());
        $this->assertSame(200, $image->getImageWidth());
        $this->assertColorNear('#ff00ff', $image, 25, 25);
        $this->assertColorNear('#000000', $image, 75, 25, 'Empty cell');
    }

    public function testSheetFollowsTheStoryChanges(): void
    {
        // Messages are handled synchronously: the handler builds the sheet right after each flush
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-triggers');
        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];
        $story = $this->createStory($workspace);

        $first = $this->createAsset(['workspace' => $workspace, 'collectionId' => $story->getStoryCollection()->getId()]);
        $this->assertNull($this->getRendition($story, $sheetDefinition), 'No item image yet');

        // An item gets its thumbnail
        $this->setThumbnail($first, $defaults, '#ff0000');
        $hash = $this->getRendition($story, $sheetDefinition)?->getBuildHash();
        $this->assertNotNull($hash, 'Built once the thumbnail of an item exists');

        // Another item is added, then gets its thumbnail
        $second = $this->createAsset(['workspace' => $workspace, 'collectionId' => $story->getStoryCollection()->getId()]);
        $this->assertSame($hash, $this->getRendition($story, $sheetDefinition)->getBuildHash());
        $this->setThumbnail($second, $defaults, '#00ff00');
        $this->assertNotSame($hash, $hash = $this->getRendition($story, $sheetDefinition)->getBuildHash());

        // Items are moved
        $relation = $this->getRelation($story, $second);
        $relation->setPosition(-1);
        self::getEntityManager()->flush();
        $this->assertNotSame($hash, $hash = $this->getRendition($story, $sheetDefinition)->getBuildHash());

        // An item is deleted
        $second->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();
        $this->assertNotSame($hash, $hash = $this->getRendition($story, $sheetDefinition)->getBuildHash());

        // An item is quarantined
        $first->setStatus(AssetStatusEnum::Quarantined);
        self::getEntityManager()->flush();
        $this->assertNull($this->getRendition($story, $sheetDefinition), 'No item left');
    }

    public function testThumbnailsOfStoriesInTheApiOutput(): void
    {
        $this->interceptBuilds();
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-output');
        $sheetDefinition = $defaults->renditionDefinitions['storyContactSheet'];

        $story = $this->createStory($workspace);
        $item = $this->createItem($story, $defaults, '#ff0000', source: true);
        self::getService(RenditionBuilder::class)->buildRendition($sheetDefinition, $story);
        $sheetFileId = $this->getRendition($story, $sheetDefinition)->getFile()->getId();

        $data = $this->getAssetOutput($story);
        $this->assertSame($sheetFileId, $data['thumbnail']['file']['id'] ?? null, 'Without cover, the sheet is the thumbnail');
        $this->assertSame($sheetFileId, $data['animatedThumbnail']['file']['id'] ?? null);

        // A cover: its thumbnail is the story thumbnail, the sheet shows on hover
        $em = self::getEntityManager();
        $workspace = $em->find(Workspace::class, $workspace->getId());
        $story = $em->find(Asset::class, $story->getId());
        $item = $em->find(Asset::class, $item->getId());
        $story->setSource($item->getSource());
        $coverThumbnail = $this->createImageFile($workspace, '#123456');
        $this->createAssetRendition($story, self::getEntityManager()->find(RenditionDefinition::class, $defaults->renditionDefinitions['thumbnail']->getId()), $coverThumbnail);

        $data = $this->getAssetOutput($story);
        $this->assertSame($coverThumbnail->getId(), $data['thumbnail']['file']['id'] ?? null);
        $this->assertSame($sheetFileId, $data['animatedThumbnail']['file']['id'] ?? null);

        // A regular asset is not concerned
        $item = self::getEntityManager()->find(Asset::class, $item->getId());
        $data = $this->getAssetOutput($item);
        $this->assertSame($this->getThumbnailFileId($item, $defaults), $data['thumbnail']['file']['id'] ?? null);
    }

    public function testGenerateDefinitionsAreValidated(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('sheet-validation', KeycloakClientTestMock::USER_UID);
        $payload = [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'policy' => '/rendition-policies/'.$defaults->renditionPolicy->getId(),
            'name' => 'Sheet',
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'target' => AssetTypeEnum::Story->value,
            'definition' => "generate:\n    transformations:\n        - module: contact_sheet\n          options: {grid: 4}\n",
        ];

        $this->jsonRequest('POST', '/rendition-definitions', KeycloakClientTestMock::USER_UID, array_merge($payload, [
            'parent' => '/rendition-definitions/'.$defaults->renditionDefinitions['main']->getId(),
        ]));
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/rendition-definitions', KeycloakClientTestMock::USER_UID, array_merge($payload, [
            'target' => AssetTypeEnum::Asset->value,
        ]));
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/rendition-definitions', KeycloakClientTestMock::USER_UID, array_merge($payload, [
            'definition' => "generate:\n    transformations:\n        - module: contact_sheet\n          options: {grid: 3}\n",
        ]));
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/rendition-definitions', KeycloakClientTestMock::USER_UID, array_merge($payload, [
            'definition' => "generate:\n    transformations:\n        - module: imagine\n",
        ]));
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/rendition-definitions', KeycloakClientTestMock::USER_UID, $payload);
        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * Keeps the BuildStoryContactSheet messages (and the workflows) from being handled.
     */
    private function interceptBuilds(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');

        return $transport->intercept();
    }

    /**
     * @return BuildStoryContactSheet[]
     */
    private function getScheduledMessages(InMemoryTransport $transport): array
    {
        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof BuildStoryContactSheet) {
                $messages[] = $envelope->getMessage();
            }
        }

        return $messages;
    }

    /**
     * @return string[]
     */
    private function getScheduledStoryIds(InMemoryTransport $transport): array
    {
        return array_map(fn (BuildStoryContactSheet $m): string => $m->getStoryAssetId(), $this->getScheduledMessages($transport));
    }

    private function createStory(Workspace $workspace): Asset
    {
        $story = $this->createAsset(['workspace' => $workspace, 'no_flush' => true]);
        self::getService(AssetManager::class)->turnIntoStory($story);
        self::getEntityManager()->flush();

        return $story;
    }

    /**
     * @param string|null $color  the color of its thumbnail, none without
     * @param bool        $source whether it has a (fetchable) source file
     */
    private function createItem(Asset $story, WorkspaceDefaults $defaults, ?string $color, bool $source = false): Asset
    {
        $workspace = $story->getWorkspace();
        $item = $this->createAsset([
            'workspace' => $workspace,
            'collectionId' => $story->getStoryCollection()->getId(),
            'no_flush' => $source,
        ]);
        if ($source) {
            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setPath('test/'.uniqid().'-missing.jpg');
            $file->setType('image/jpeg');
            self::getEntityManager()->persist($file);
            $item->setSource($file);
            self::getEntityManager()->flush();
        }

        if (null !== $color) {
            $this->createAssetRendition($item, $defaults->renditionDefinitions['thumbnail'], $this->createImageFile($workspace, $color));
        }

        return $item;
    }

    private function setThumbnail(Asset $item, WorkspaceDefaults $defaults, string $color): void
    {
        self::getService(RenditionManager::class)->createOrReplaceRenditionFile(
            $item,
            $defaults->renditionDefinitions['thumbnail'],
            $this->createImageFile($item->getWorkspace(), $color, flush: false),
            null,
            null,
        );
        self::getEntityManager()->flush();
    }

    private function createImageFile(Workspace $workspace, string $color, bool $flush = true): File
    {
        $image = new \Imagick();
        $image->newImage(300, 200, new \ImagickPixel($color));
        $image->setImageFormat('png');

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.png');
        $file->setType('image/png');
        $file->setExtension('png');
        self::getService(FileStorageManager::class)->store($file->getPath(), $image->getImageBlob());

        $em = self::getEntityManager();
        $em->persist($file);
        if ($flush) {
            $em->flush();
        }

        return $file;
    }

    private function readImage(File $file): \Imagick
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet-test-');
        $this->tmpFiles[] = $path;
        $stream = self::getService(FileStorageManager::class)->getStream($file->getPath());
        file_put_contents($path, $stream);
        fclose($stream);

        return new \Imagick($path);
    }

    private function getRendition(Asset $asset, RenditionDefinition $definition): ?AssetRendition
    {
        return self::getEntityManager()->getRepository(AssetRendition::class)->findOneBy([
            'asset' => $asset->getId(),
            'definition' => $definition->getId(),
        ]);
    }

    private function getRelation(Asset $story, Asset $item): CollectionAsset
    {
        /** @var CollectionAssetRepository $repository */
        $repository = self::getEntityManager()->getRepository(CollectionAsset::class);

        return $repository->findCollectionAsset($item->getId(), $story->getStoryCollection()->getId());
    }

    private function getThumbnailFileId(Asset $item, WorkspaceDefaults $defaults): ?string
    {
        return $this->getRendition($item, $defaults->renditionDefinitions['thumbnail'])?->getFile()?->getId();
    }

    private function getAssetOutput(Asset $asset): array
    {
        self::getEntityManager()->flush();

        return $this->jsonRequest('GET', '/assets/'.$asset->getId(), KeycloakClientTestMock::ADMIN_UID)->toArray();
    }

    private function assertColorNear(string $expected, \Imagick $image, int $x, int $y, string $message = ''): void
    {
        $color = $image->getImagePixelColor($x, $y)->getColor();
        $expectedRgb = sscanf($expected, '#%02x%02x%02x');
        foreach (['r', 'g', 'b'] as $i => $channel) {
            // Lossy (webp) output
            $this->assertEqualsWithDelta($expectedRgb[$i], $color[$channel], 16, trim(sprintf('%s: %s at %d,%d, got %s', $message, $expected, $x, $y, json_encode($color))));
        }
    }
}
