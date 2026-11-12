<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Util\FilesystemUtils;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Attribute\AttributeTypeRegistry;
use App\Automation\Action\ShareExportAction;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\Export\RemoteFilesystemFactory;
use App\Automation\UserContextSwitcher;
use App\Entity\Automation\AutomationShare;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Model\AutomationTriggerEnum;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Share\ShareRenditionUrlGenerator;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\UserSpace\UserSpaceTestTrait;
use App\Tests\Functional\Automation\AutomationTestTrait;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;

final class ShareExportActionTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;
    use AutomationTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string CLIENT_URL = 'https://databox.test';
    private const string UNKNOWN_ID = '00000000-0000-4000-8000-000000000000';

    private Workspace $workspace;
    private SavedSearchAutomation $automation;
    private string $remoteDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->automation = $this->createAutomationEntity(
            $this->createSavedSearchWith(self::OWNER, ['query' => '']),
            [],
            options: ['name' => 'Daily export'],
        );
        $this->remoteDir = sys_get_temp_dir().'/'.uniqid('share-export-test', true);
        mkdir($this->remoteDir);
    }

    protected function tearDown(): void
    {
        FilesystemUtils::rrmdir($this->remoteDir);
        parent::tearDown();
    }

    /**
     * The destination is a local directory.
     */
    private function createLocalFactory(): RemoteFilesystemFactory
    {
        return new class($this->remoteDir) extends RemoteFilesystemFactory {
            public function __construct(private readonly string $dir)
            {
            }

            protected function createAdapter(?string $protocol, array $options): FilesystemAdapter
            {
                return new LocalFilesystemAdapter($this->dir);
            }
        };
    }

    private function createAction(?RemoteFilesystemFactory $factory = null): ShareExportAction
    {
        $em = self::getEntityManager();
        $action = new ShareExportAction(
            $em,
            $factory ?? $this->createLocalFactory(),
            $em->getRepository(AutomationShare::class),
            self::getService(ShareRenditionUrlGenerator::class),
            self::getService(AttributesResolver::class),
            self::getService(AttributeTypeRegistry::class),
            self::getService(AssetNameResolver::class),
            self::CLIENT_URL,
        );
        $action->setSecurity(self::getService(Security::class));
        $action->setRoleMapper(self::getService(RoleMapper::class));

        return $action;
    }

    /**
     * Runs the action on batches of assets as the runner does (the entity manager cleared between
     * batches), with a configuration validated by the owner.
     *
     * @param Asset[][] $batches
     */
    private function process(array $config, array $batches, string $userId = self::OWNER, ?ShareExportAction $action = null, bool $withWorkspace = true): AutomationActionState
    {
        $action ??= $this->createAction();
        $state = new AutomationActionState(ShareExportAction::TYPE);
        $em = self::getEntityManager();
        $context = new AutomationRunContext(
            runId: 'run-1',
            automationId: $this->automation->getId(),
            automationName: 'Daily export',
            savedSearchId: $this->automation->getSavedSearch()->getId(),
            savedSearchName: 'Automated search',
            ownerId: $userId,
            trigger: AutomationTriggerEnum::Manual,
            dryRun: false,
            workspaceId: $withWorkspace ? $this->workspace->getId() : null,
        );

        $config = $this->validate($config, $action);
        self::getService(UserContextSwitcher::class)->runAs($userId, function () use ($action, $config, $batches, $state, $em, $context): void {
            foreach ($batches as $assets) {
                $em->clear();
                $assets = array_map(fn (Asset $asset): Asset => $em->find(Asset::class, $asset->getId()), $assets);
                $state->beginBatch();
                $action->processBatch(new AutomationBatch($assets, $config, $state, $context));
            }
            $action->finish($state, $config, $context);
        });
        $em->clear();

        return $state;
    }

    private function validate(array $config, ?ShareExportAction $action = null): array
    {
        $action ??= $this->createAction();

        return self::getService(UserContextSwitcher::class)->runAs(self::OWNER, fn (): array => $action->validateConfig($config));
    }

    private static function config(array $overrides = []): array
    {
        return array_merge([
            'protocol' => 's3',
            's3' => [
                'bucket' => 'exports',
                'accessKeyId' => 'AKIA',
                'secretAccessKey' => 's3cr3t',
            ],
            'renditionIds' => [self::UNKNOWN_ID],
        ], $overrides);
    }

    /**
     * @param RenditionDefinition[] $definitions
     */
    private static function renditionConfig(array $definitions, array $overrides = []): array
    {
        return self::config([
            'renditionIds' => array_map(fn (RenditionDefinition $definition): string => $definition->getId(), $definitions),
        ] + $overrides);
    }

    private function createDefinition(string $name, bool $public = true, ?Workspace $workspace = null): RenditionDefinition
    {
        $em = self::getEntityManager();
        $workspace = $em->find(Workspace::class, ($workspace ?? $this->workspace)->getId());
        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName($name.' policy');
        $policy->setPublic($public);
        $policy->setEditable(false);
        $em->persist($policy);

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName($name);
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_NONE);
        $em->persist($definition);
        $em->flush();

        return $definition;
    }

    private function createAssetWithRenditions(string $name, array $definitions, array $options = []): Asset
    {
        $em = self::getEntityManager();
        $workspace = $em->find(Workspace::class, ($options['workspace'] ?? $this->workspace)->getId());
        $asset = $this->createAsset([
            'ownerId' => self::OWNER,
            'name' => $name,
        ] + $options + ['workspace' => $workspace]);

        foreach ($definitions as $definition) {
            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setPath('files/'.$name.'-'.$definition->getName());
            $file->setType('image/jpeg');
            $em->persist($file);

            $rendition = new AssetRendition();
            $rendition->setAsset($em->find(Asset::class, $asset->getId()));
            $rendition->setDefinition($em->find(RenditionDefinition::class, $definition->getId()));
            $rendition->setFile($file);
            $em->persist($rendition);
        }
        $em->flush();

        return $asset;
    }

    /**
     * @return string[] the exported files, relative to the destination
     */
    private function listRemoteFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->remoteDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($this->remoteDir) + 1);
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<list<string>> the rows of the single exported file, header included
     */
    private function readExport(): array
    {
        $files = $this->listRemoteFiles();
        $this->assertCount(1, $files, 'A single file is exported');

        $reader = new Reader();
        $reader->open($this->remoteDir.'/'.$files[0]);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn (mixed $value): string => (string) $value, $row->toArray());
            }
            break;
        }
        $reader->close();

        // Trailing empty cells are not stored
        $width = count($rows[0]);

        return array_map(fn (array $row): array => array_pad($row, $width, ''), $rows);
    }

    private function findAutomationShare(?Workspace $workspace = null): ?AutomationShare
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->getRepository(AutomationShare::class)->findOneBy([
            'automation' => $this->automation->getId(),
            'workspace' => ($workspace ?? $this->workspace)->getId(),
        ]);
    }

    private static function assertRenditionUrl(Share $share, Asset $asset, RenditionDefinition $definition, string $url): void
    {
        self::assertStringEndsWith(sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $asset->getId(), $definition->getId(), $share->getToken()), $url);
    }

    public function testCreatesTheShareAndUploadsTheExport(): void
    {
        $preview = $this->createDefinition('preview');
        $hd = $this->createDefinition('hd', public: false);
        $description = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Description',
            'slug' => 'description',
        ]);
        $collection = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'My Folder']);
        $first = $this->createAssetWithRenditions('first', [$preview, $hd], [
            'collectionId' => $collection->getId(),
            'attributes' => [['definition' => $description, 'value' => '=1+1']],
        ]);
        $second = $this->createAssetWithRenditions('second', []);

        $state = $this->process(self::renditionConfig([$preview, $hd], [
            'attributeIds' => [$description->getId(), self::UNKNOWN_ID],
        ]), [[$first, $second]]);

        $this->assertSame(2, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(0, $state->getSkipped());

        $share = $this->findAutomationShare()->getShare();
        $this->assertSame('Automated search Automation Daily export', $share->getName());
        $this->assertSame(self::OWNER, $share->getOwnerId());
        $this->assertNull($share->getExpiresAt());
        $this->assertTrue($share->isRenditionsRestricted());
        $this->assertSame([$preview->getId()], array_map(fn (RenditionDefinition $d): string => $d->getId(), $share->getRenditionDefinitions()->getValues()));
        $assetIds = array_map(fn (Asset $a): string => $a->getId(), $share->getAssetsList());
        sort($assetIds);
        $expectedIds = [$first->getId(), $second->getId()];
        sort($expectedIds);
        $this->assertSame($expectedIds, $assetIds);

        $files = $this->listRemoteFiles();
        $this->assertMatchesRegularExpression('#^Automated-search/Daily-export-\d{8}-\d{6}\.xlsx$#', $files[0]);

        $rows = $this->readExport();
        $this->assertCount(3, $rows);
        $this->assertSame(['ID', 'Title', 'Collection', 'Created at', 'Share URL', 'preview', 'hd', 'Description', self::UNKNOWN_ID], $rows[0]);

        $byId = array_column(array_slice($rows, 1), null, 0);
        $row = $byId[$first->getId()];
        $this->assertSame('My Folder', $row[2]);
        $this->assertNotSame('', $row[3]);
        $this->assertSame(sprintf('%s/s/%s/%s', self::CLIENT_URL, $share->getId(), $share->getToken()), $row[4]);
        self::assertRenditionUrl($share, $first, $preview, $row[5]);
        // Not public: never in a share
        $this->assertSame('', $row[6]);
        // Written as a text, not as a formula
        $this->assertSame('=1+1', $row[7]);
        $this->assertSame('', $row[8]);

        $row = $byId[$second->getId()];
        $this->assertSame($row[4], $byId[$first->getId()][4]);
        // No rendition
        $this->assertSame('', $row[5]);

        $messages = array_column($state->getErrors(), 'message');
        $this->assertContains('Rendition "hd" of workspace "My workspace" cannot be shared: its policy is not public', $messages);
        $this->assertContains(sprintf('2 asset(s) exported to "%s"', $files[0]), $messages);
    }

    public function testTheShareIsReusedAndCreatedAgainOnceDeleted(): void
    {
        $preview = $this->createDefinition('preview');
        $first = $this->createAssetWithRenditions('first', [$preview]);
        $second = $this->createAssetWithRenditions('second', [$preview]);
        $third = $this->createAssetWithRenditions('third', [$preview]);

        // Two batches: the share is found again after the entity manager is cleared
        $this->process(self::renditionConfig([$preview]), [[$first], [$second]]);
        $shareId = $this->findAutomationShare()->getShare()->getId();
        $this->assertCount(2, $this->findAutomationShare()->getShare()->getAssetsList());
        FilesystemUtils::rrmdir($this->remoteDir);
        mkdir($this->remoteDir);

        $this->process(self::renditionConfig([$preview]), [[$third]]);
        $share = $this->findAutomationShare()->getShare();
        $this->assertSame($shareId, $share->getId());
        $this->assertCount(3, $share->getAssetsList());
        // The file lists the assets of the run only
        $rows = $this->readExport();
        $this->assertCount(2, $rows);
        $this->assertSame($third->getId(), $rows[1][0]);
        self::assertRenditionUrl($share, $third, $preview, $rows[1][5]);

        // The link goes with its share (ON DELETE CASCADE, not enforced by SQLite)
        $em = self::getEntityManager();
        $em->remove($this->findAutomationShare());
        $em->remove($em->find(Share::class, $shareId));
        $em->flush();
        $this->assertNull($this->findAutomationShare());

        $this->process(self::renditionConfig([$preview]), [[$first]]);
        $share = $this->findAutomationShare()->getShare();
        $this->assertNotSame($shareId, $share->getId());
        $this->assertSame([$first->getId()], array_map(fn (Asset $a): string => $a->getId(), $share->getAssetsList()));
    }

    public function testAssetsOfAnotherWorkspaceAreSkipped(): void
    {
        $other = $this->createOtherWorkspace(self::OWNER, 'other');
        $preview = $this->createDefinition('preview');
        $otherPreview = $this->createDefinition('preview', workspace: $other);
        $first = $this->createAssetWithRenditions('first', [$preview]);
        $second = $this->createAssetWithRenditions('second', [$otherPreview], ['workspace' => $other]);

        $state = $this->process(self::renditionConfig([$preview]), [[$first, $second]]);
        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(1, $state->getSkipped());
        $this->assertContains(['assetId' => $second->getId(), 'message' => 'The asset belongs to another workspace'], $state->getErrors());

        $share = $this->findAutomationShare()->getShare();
        $this->assertSame([$first->getId()], array_map(fn (Asset $a): string => $a->getId(), $share->getAssetsList()));
        $this->assertNull($this->findAutomationShare($other));

        $rows = $this->readExport();
        $this->assertCount(2, $rows);
        $this->assertSame($first->getId(), $rows[1][0]);
        self::assertRenditionUrl($share, $first, $preview, $rows[1][5]);
    }

    public function testWithoutWorkspaceTheAssetsFail(): void
    {
        $preview = $this->createDefinition('preview');
        $first = $this->createAssetWithRenditions('first', [$preview]);
        $second = $this->createAssetWithRenditions('second', [$preview]);

        $state = $this->process(self::renditionConfig([$preview], ['uploadWhenEmpty' => true]), [[$first, $second]], withWorkspace: false);

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(2, $state->getFailed());
        $this->assertContains(['assetId' => $first->getId(), 'message' => 'The automation has no workspace: edit it to choose one'], $state->getErrors());
        $this->assertNull($this->findAutomationShare());
        $this->assertSame([], $this->listRemoteFiles());
    }

    public function testADeletedRenditionIsReported(): void
    {
        $preview = $this->createDefinition('preview');
        $asset = $this->createAssetWithRenditions('first', [$preview]);

        $state = $this->process(self::config(['renditionIds' => [$preview->getId(), self::UNKNOWN_ID]]), [[$asset]]);

        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertContains(sprintf('Rendition %s no longer exists', self::UNKNOWN_ID), array_column($state->getErrors(), 'message'));
        $share = $this->findAutomationShare()->getShare();
        $rows = $this->readExport();
        $this->assertSame(['ID', 'Title', 'Collection', 'Created at', 'Share URL', 'preview', self::UNKNOWN_ID], $rows[0]);
        self::assertRenditionUrl($share, $asset, $preview, $rows[1][5]);
        $this->assertSame('', $rows[1][6]);
    }

    public function testAssetsWhichCannotBeSharedAreSkipped(): void
    {
        $preview = $this->createDefinition('preview');
        $readOnly = $this->createAssetWithRenditions('read only', [$preview]);
        $shareable = $this->createAssetWithRenditions('shareable', [$preview]);
        $other = KeycloakClientTestMock::OTHER_USER_UID;
        $this->addUserOnWorkspace($other, $this->workspace->getId());
        $this->grantUserOnObject($other, $readOnly, PermissionInterface::VIEW);
        $this->grantUserOnObject($other, $shareable, PermissionInterface::VIEW | PermissionInterface::SHARE);

        $state = $this->process(self::renditionConfig([$preview]), [[$readOnly, $shareable]], $other);

        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(1, $state->getSkipped());
        $this->assertContains(['assetId' => $readOnly->getId(), 'message' => 'Not allowed to share the asset'], $state->getErrors());

        $share = $this->findAutomationShare()->getShare();
        $this->assertSame($other, $share->getOwnerId());
        $this->assertSame([$shareable->getId()], array_map(fn (Asset $a): string => $a->getId(), $share->getAssetsList()));
        $rows = $this->readExport();
        $this->assertCount(2, $rows);
        $this->assertSame($shareable->getId(), $rows[1][0]);
    }

    public function testWithoutAnyPublicRenditionNoShareIsCreated(): void
    {
        $hd = $this->createDefinition('hd', public: false);
        $asset = $this->createAssetWithRenditions('first', [$hd]);

        $state = $this->process(self::renditionConfig([$hd]), [[$asset]]);

        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertNull($this->findAutomationShare());
        $messages = array_column($state->getErrors(), 'message');
        $this->assertContains('Rendition "hd" of workspace "My workspace" cannot be shared: its policy is not public', $messages);
        $this->assertContains('None of the renditions can be shared in workspace "My workspace": its assets are listed without links', $messages);

        $rows = $this->readExport();
        $this->assertCount(2, $rows);
        $this->assertSame($asset->getId(), $rows[1][0]);
        $this->assertSame('', $rows[1][4]);
        $this->assertSame('', $rows[1][5]);
    }

    public function testNothingIsUploadedWithoutAssetsUnlessAsked(): void
    {
        $preview = $this->createDefinition('preview');

        $state = $this->process(self::renditionConfig([$preview]), []);
        $this->assertSame([], $this->listRemoteFiles());
        $this->assertSame([], $state->getErrors());

        $this->process(self::renditionConfig([$preview], [
            'uploadWhenEmpty' => true,
            'pathTemplate' => 'reports/{automation}/{date}-{runId}.xlsx',
        ]), []);
        $files = $this->listRemoteFiles();
        $this->assertMatchesRegularExpression('#^reports/Daily-export/\d{4}-\d{2}-\d{2}-run-1\.xlsx$#', $files[0]);
        $this->assertSame([['ID', 'Title', 'Collection', 'Created at', 'Share URL', 'preview']], $this->readExport());
    }

    public function testAnUnreachableDestinationFailsTheRun(): void
    {
        $preview = $this->createDefinition('preview');
        $asset = $this->createAssetWithRenditions('first', [$preview]);

        try {
            $this->process(self::renditionConfig([$preview], [
                'protocol' => 'ftp',
                'ftp' => ['host' => '127.0.0.1', 'port' => 1, 'username' => 'anonymous'],
            ]), [[$asset]], action: $this->createAction(new RemoteFilesystemFactory()));
            $this->fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('Destination unavailable: ', $e->getMessage());
        }

        // The asset was added to the share anyway
        $this->assertCount(1, $this->findAutomationShare()->getShare()->getAssetsList());
    }

    public function testConfigIsNormalized(): void
    {
        $preview = $this->createDefinition('preview');
        $title = '11111111-1111-4111-8111-111111111111';
        $keywords = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

        $this->assertSame([
            'protocol' => 'ftp',
            'pathTemplate' => '{savedSearch}/{automation}-{datetime}.xlsx',
            'renditionIds' => [$preview->getId()],
            'attributeIds' => [$title, $keywords],
            'locale' => 'fr',
            'uploadWhenEmpty' => false,
            'ftp' => [
                'host' => 'ftp.example.com',
                'port' => 21,
                'username' => 'bob',
                'password' => null,
                'ssl' => false,
                'passive' => true,
                'root' => '',
            ],
        ], $this->validate([
            'protocol' => 'ftp',
            'pathTemplate' => '',
            'renditionIds' => [' '.$preview->getId(), '/rendition-definitions/'.$preview->getId()],
            'attributeIds' => [$title, ' '.strtoupper($keywords).' ', $title],
            'locale' => 'fr',
            'ftp' => ['host' => 'ftp.example.com', 'username' => 'bob'],
            's3' => ['bucket' => 'b'],
        ]));
    }

    public static function invalidConfigs(): array
    {
        return [
            'unknown protocol' => [self::config(['protocol' => 'webdav']), 'protocol'],
            'missing bucket' => [self::config(['s3' => ['accessKeyId' => 'a']]), 's3.bucket'],
            'no rendition' => [self::config(['renditionIds' => []]), 'renditionIds'],
            'blank rendition' => [self::config(['renditionIds' => ['']]), 'renditionIds'],
            'rendition name' => [self::config(['renditionIds' => ['preview']]), 'renditionIds'],
            'parent directory' => [self::config(['pathTemplate' => '../{automation}.xlsx']), 'pathTemplate'],
            'asset variable' => [self::config(['pathTemplate' => '{assetId}.xlsx']), 'pathTemplate'],
            'too many attributes' => [self::config(['attributeIds' => array_map(fn (int $i): string => sprintf('00000000-0000-4000-8000-%012d', $i), range(1, ShareExportAction::MAX_ATTRIBUTES + 1))]), 'attributeIds'],
            'invalid attribute' => [self::config(['attributeIds' => [42]]), 'attributeIds'],
            'attribute slug' => [self::config(['attributeIds' => ['title']]), 'attributeIds'],
            'invalid locale' => [self::config(['locale' => 'fr fr']), 'locale'],
            'invalid uploadWhenEmpty' => [self::config(['uploadWhenEmpty' => 'yes']), 'uploadWhenEmpty'],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigs(array $config, string $path): void
    {
        try {
            $this->validate($config);
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertSame($path, $e->getConstraintViolationList()->get(0)->getPropertyPath());
        }
    }

    private function getAutomationViolations(array $config, ?Workspace $workspace): array
    {
        $em = self::getEntityManager();
        $automation = $em->find(SavedSearchAutomation::class, $this->automation->getId());
        $automation->setWorkspace(null !== $workspace ? $em->find(Workspace::class, $workspace->getId()) : null);
        $action = $this->createAction();

        return self::getService(UserContextSwitcher::class)->runAs(self::OWNER, fn (): array => $action->getAutomationViolations($this->validate($config, $action), $automation));
    }

    public function testAutomationViolations(): void
    {
        $other = $this->createOtherWorkspace(self::OWNER, 'other');
        $preview = $this->createDefinition('preview');
        $otherPreview = $this->createDefinition('preview', workspace: $other);
        $description = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Description',
            'slug' => 'description',
        ]);
        $otherDescription = $this->createAttributeDefinition([
            'workspace' => $other,
            'name' => 'Description',
            'slug' => 'description',
        ]);

        $this->assertSame([
            'workspace' => 'Choose the workspace of the automation: this action only works on one workspace.',
        ], $this->getAutomationViolations(self::renditionConfig([$preview]), null));

        $this->assertSame([], $this->getAutomationViolations(self::renditionConfig([$preview], [
            'attributeIds' => [$description->getId()],
        ]), $this->workspace));

        $this->assertSame([
            'renditionIds' => sprintf('Rendition "%s" not found in the workspace.', $otherPreview->getId()),
        ], $this->getAutomationViolations(self::renditionConfig([$preview, $otherPreview]), $this->workspace));

        $this->assertSame([
            'renditionIds' => sprintf('Rendition "%s" not found in the workspace.', self::UNKNOWN_ID),
            'attributeIds' => sprintf('Attribute "%s" not found in the workspace.', $otherDescription->getId()),
        ], $this->getAutomationViolations(self::config([
            'attributeIds' => [$description->getId(), $otherDescription->getId()],
        ]), $this->workspace));
    }
}
