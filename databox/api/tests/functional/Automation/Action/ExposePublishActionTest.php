<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\Action\ExposePublishAction;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\UserSpace\UserSpaceTestTrait;
use App\Tests\Functional\Automation\AutomationTestTrait;
use App\Tests\Functional\Integration\Expose\ExposeHttpClientMock;
use App\Tests\Functional\Integration\Expose\ExposeTestTrait;
use App\Tests\Functional\Integration\Expose\FakeExposeApi;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class ExposePublishActionTest extends AbstractDataboxTestCase
{
    use AutomationTestTrait;
    use ExposeTestTrait;
    use UserSpaceTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string AUTOMATION = 'automation-1';

    private FakeExposeApi $api;
    private Workspace $workspace;
    private WorkspaceIntegration $integration;
    private string $publicationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new FakeExposeApi();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->integration = $this->createExposeIntegration(null, self::OWNER);
        $this->createExposeToken($this->integration, self::OWNER);
        $this->publicationId = $this->api->addPublication('Press kit');
        ExposeHttpClientMock::$api = $this->api;
    }

    protected function tearDown(): void
    {
        ExposeHttpClientMock::$api = null;
        parent::tearDown();
    }

    private function createAction(): ExposePublishAction
    {
        $client = self::createExposeClient($this->api);
        $action = new ExposePublishAction(
            self::getEntityManager(),
            self::getService(IntegrationManager::class),
            self::getService(IntegrationTokenRepository::class),
            $client,
            self::createPublisher($client),
            new NullLogger(),
        );
        $action->setSecurity(self::getService(Security::class));
        $action->setRoleMapper(self::getService(RoleMapper::class));

        return $action;
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'integrationId' => $this->integration->getId(),
            'publicationId' => $this->publicationId,
        ], $overrides);
    }

    private function validate(array $config, string $userId = self::OWNER): array
    {
        $action = $this->createAction();

        return self::getService(UserContextSwitcher::class)->runAs($userId, fn (): array => $action->validateConfig($config));
    }

    /**
     * Runs the action as the runner does, on batches of assets, then finishes it.
     *
     * @param list<array{0: Asset[], 1?: Asset[]}> $batches      the assets to process, and the other assets
     *                                                           of the results (failed by a previous action)
     * @param bool                                 $runTruncated whether the run stopped at its limit of assets
     *                                                           after these batches
     */
    private function publish(array $config, array $batches, AutomationScopeEnum $scope = AutomationScopeEnum::All, string $automationId = self::AUTOMATION, bool $runTruncated = false): AutomationActionState
    {
        $config = $this->validate($config);
        $action = $this->createAction();
        $state = new AutomationActionState(ExposePublishAction::TYPE);
        $em = self::getEntityManager();
        $context = new AutomationRunContext(
            runId: 'run',
            automationId: $automationId,
            automationName: 'Publish',
            savedSearchId: 'search',
            savedSearchName: 'Search',
            ownerId: self::OWNER,
            trigger: AutomationTriggerEnum::Manual,
            dryRun: false,
            scope: $scope,
        );

        self::getService(UserContextSwitcher::class)->runAs(self::OWNER, function () use ($action, $config, $batches, $state, $em, $context, $runTruncated): void {
            foreach ($batches as $batch) {
                $em->clear();
                $reload = fn (Asset $asset): Asset => $em->find(Asset::class, $asset->getId());
                $assets = array_map($reload, $batch[0]);
                $matched = array_map(fn (Asset $asset): string => $asset->getId(), array_merge($batch[0], $batch[1] ?? []));
                $state->beginBatch();
                $action->processBatch(new AutomationBatch($assets, $config, $state, $context, $matched));
            }
            $state->runTruncated = $runTruncated;
            $action->finish($state, $config, $context);
        });
        $em->clear();

        return $state;
    }

    private function asset(string $name): Asset
    {
        return $this->createPublishableAsset($this->workspace, self::OWNER, $name);
    }

    /**
     * @return array<string, array> the assets of the publication, by title
     */
    private function published(): array
    {
        $assets = [];
        foreach ($this->api->getPublicationAssets($this->publicationId) as $asset) {
            $assets[(string) $asset['title']] = $asset;
        }
        ksort($assets);

        return $assets;
    }

    public function testConfigIsNormalized(): void
    {
        $this->assertSame([
            'integrationId' => $this->integration->getId(),
            'publicationId' => $this->publicationId,
            'publicationTitle' => 'Press kit',
            'ignoreRoi' => false,
            'removeMissing' => true,
        ], $this->validate($this->config([
            'integrationId' => '/integrations/'.$this->integration->getId(),
            'publicationTitle' => 'Ignored',
            'removeMissing' => true,
        ])));
    }

    public static function invalidConfigs(): array
    {
        return [
            'missing integration' => [['integrationId' => ''], 'integrationId'],
            'unknown integration' => [['integrationId' => '0b1e2f53-27b4-4b0a-9b33-0f0b8b2a1c6d'], 'integrationId'],
            'missing publication' => [['publicationId' => null], 'publicationId'],
            'unknown publication' => [['publicationId' => 'unknown'], 'publicationId'],
            'not a boolean' => [['ignoreRoi' => 'yes'], 'ignoreRoi'],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigs(array $overrides, string $path): void
    {
        $this->assertViolation($path, fn () => $this->validate($this->config($overrides)));
    }

    public function testTheIntegrationMustBeAuthorized(): void
    {
        $em = self::getEntityManager();
        foreach ($em->getRepository(IntegrationToken::class)->findAll() as $token) {
            $em->remove($token);
        }
        $em->flush();

        $this->assertViolation('integrationId', fn () => $this->validate($this->config()), 'Authorize the Expose integration first.');
    }

    public function testTheIntegrationMustBeUsable(): void
    {
        $other = $this->createExposeIntegration(null, 'someone-else');
        $this->createExposeToken($other, self::OWNER);
        $this->assertViolation('integrationId', fn () => $this->validate($this->config(['integrationId' => $other->getId()])));

        $disabled = $this->createExposeIntegration(null, self::OWNER, enabled: false);
        $this->createExposeToken($disabled, self::OWNER);
        $this->assertViolation('integrationId', fn () => $this->validate($this->config(['integrationId' => $disabled->getId()])));
    }

    public function testThePublicationMustBeEditable(): void
    {
        $readOnly = $this->api->addPublication('Read only', editable: false);

        $this->assertViolation('publicationId', fn () => $this->validate($this->config(['publicationId' => $readOnly])));
    }

    public function testRemovingTheMissingAssetsRequiresTheScopeAll(): void
    {
        $savedSearch = $this->createSavedSearchWith(self::OWNER, ['query' => 'cats']);
        $payload = [
            'savedSearch' => $savedSearch->getId(),
            'name' => 'Publish cats',
            'scope' => 'new',
            'actions' => [
                ['type' => ExposePublishAction::TYPE, 'config' => $this->config(['removeMissing' => true])],
            ],
        ];

        $response = $this->api('POST', '/saved-search-automations', self::OWNER, $payload);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['actions[0].config.removeMissing'], array_column($response->toArray(false)['violations'], 'propertyPath'));

        $response = $this->api('POST', '/saved-search-automations', self::OWNER, ['scope' => 'all'] + $payload);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame('Press kit', $data['actions'][0]['config']['publicationTitle']);

        // Changing the scope alone is checked against the stored actions
        $response = $this->api('PATCH', '/saved-search-automations/'.$data['id'], self::OWNER, ['scope' => 'new']);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['scope'], array_column($response->toArray(false)['violations'], 'propertyPath'));
        $this->assertSame(AutomationScopeEnum::All, $this->findAutomation($data['id'])->getScope());
    }

    public function testPublishesTheAssets(): void
    {
        $photo = $this->asset('photo.jpg');
        $other = $this->asset('other.jpg');

        $state = $this->publish($this->config(), [[[$photo, $other]]]);

        $this->assertSame(2, $state->getProcessed(), json_encode($state->getErrors()));
        $published = $this->published();
        $this->assertSame(['other.jpg', 'photo.jpg'], array_keys($published));
        $this->assertTrue($published['photo.jpg']['uploaded']);
        $this->assertSame($photo->getId(), $published['photo.jpg']['assetId']);
        $this->assertSame([
            'automationId' => self::AUTOMATION,
            'assetId' => $photo->getId(),
            'fileId' => $photo->getSource()->getId(),
        ], json_decode((string) $published['photo.jpg']['clientAnnotations'], true));
        $this->assertSame(['preview', 'thumbnail'], array_keys($this->api->getSubDefinitions($published['photo.jpg']['id'])));
    }

    public function testRunningAgainSendsOnlyWhatChanged(): void
    {
        $unchanged = $this->asset('unchanged.jpg');
        $renamed = $this->asset('renamed.jpg');
        $replaced = $this->asset('replaced.jpg');
        $this->publish($this->config(), [[[$unchanged, $renamed, $replaced]]]);
        $this->api->requests = [];

        $this->publish($this->config(), [[[$unchanged, $renamed, $replaced]]]);
        $this->assertSame([], $this->api->getWrites());

        $em = self::getEntityManager();
        $em->find(File::class, $renamed->getSource()->getId())->setOriginalName('renamed-v2.jpg');
        $em->find(Asset::class, $replaced->getId())->setSource($this->createSourceFile($this->workspace, 'replaced-v2.jpg'));
        $em->flush();
        $this->api->requests = [];

        $state = $this->publish($this->config(), [[[$unchanged, $renamed, $replaced]]]);

        $this->assertSame(3, $state->getProcessed());
        $this->assertSame([
            'PATCH /assets/{id}',
            'DELETE /assets/{id}',
            'POST /assets',
            'POST /sub-definitions',
            'POST /sub-definitions',
        ], $this->api->getWrites());
        $this->assertSame(['renamed-v2.jpg', 'replaced-v2.jpg', 'unchanged.jpg'], array_keys($this->published()));
    }

    public function testMirrorRemovesTheAssetsWhichLeftTheResults(): void
    {
        $kept = $this->asset('kept.jpg');
        $left = $this->asset('left.jpg');
        $failed = $this->asset('failed.jpg');
        $this->publish($this->config(['removeMissing' => true]), [[[$kept, $left, $failed]]]);
        $this->api->addAsset($this->publicationId, ['basketAssetId' => 'b1', 'fileId' => 'f1'], 'basket');
        $this->api->addAsset($this->publicationId, ['automationId' => 'other', 'assetId' => $left->getId(), 'fileId' => 'f2'], 'other automation');

        // "failed" was failed by a previous action: it is still in the results
        $state = $this->publish($this->config(['removeMissing' => true]), [[[$kept], [$failed]]]);

        $this->assertSame([], $state->getErrors());
        $this->assertSame(['basket', 'failed.jpg', 'kept.jpg', 'other automation'], array_keys($this->published()));
    }

    public function testMirrorKeepsTheAssetsAfterTheLimitOfATruncatedRun(): void
    {
        $first = $this->asset('first.jpg');
        $second = $this->asset('second.jpg');
        $third = $this->asset('third.jpg');
        $this->publish($this->config(['removeMissing' => true]), [[[$first, $second, $third]]]);

        // A limit of 1 asset: the run only went through the first result
        $state = $this->publish($this->config(['removeMissing' => true]), [[[$first]]], runTruncated: true);

        $this->assertSame(1, $state->getProcessed());
        $this->assertSame(0, $state->getFailed());
        $this->assertSame(AutomationActionState::STATUS_SUCCESS, $state->getStatus());
        $this->assertSame([['message' => ExposePublishAction::RUN_TRUNCATED]], $state->getErrors());
        $this->assertSame(['first.jpg', 'second.jpg', 'third.jpg'], array_keys($this->published()));

        // Without the limit reached, the assets out of the results are removed
        $state = $this->publish($this->config(['removeMissing' => true]), [[[$first]]]);

        $this->assertSame([], $state->getErrors());
        $this->assertSame(['first.jpg'], array_keys($this->published()));
    }

    public function testMirrorEmptiesThePublicationWithoutResults(): void
    {
        $this->publish($this->config(['removeMissing' => true]), [[[$this->asset('gone.jpg')]]]);

        $this->publish($this->config(['removeMissing' => true]), []);

        $this->assertSame([], $this->published());
    }

    public function testWithoutMirrorNothingIsRemoved(): void
    {
        $this->publish($this->config(), [[[$this->asset('stays.jpg')]]]);

        $this->publish($this->config(), []);
        $this->publish($this->config(['removeMissing' => true]), [], AutomationScopeEnum::New);

        $this->assertSame(['stays.jpg'], array_keys($this->published()));
    }

    public function testAMissingAccessFailsEveryAsset(): void
    {
        $config = $this->validate($this->config(['removeMissing' => true]));
        $this->publish($config, [[[$this->asset('published.jpg')]]]);
        $em = self::getEntityManager();
        foreach ($em->getRepository(IntegrationToken::class)->findAll() as $token) {
            $em->remove($token);
        }
        $em->flush();

        $action = $this->createAction();
        $state = new AutomationActionState(ExposePublishAction::TYPE);
        $context = new AutomationRunContext('run', self::AUTOMATION, 'Publish', 'search', 'Search', self::OWNER, AutomationTriggerEnum::Schedule, false, AutomationScopeEnum::All);
        $assets = [$this->asset('a.jpg'), $this->asset('b.jpg')];
        self::getService(UserContextSwitcher::class)->runAs(self::OWNER, function () use ($action, $config, $state, $context, $assets): void {
            foreach ($assets as $asset) {
                $asset = self::getEntityManager()->find(Asset::class, $asset->getId());
                $state->beginBatch();
                $action->processBatch(new AutomationBatch([$asset], $config, $state, $context, [$asset->getId()]));
            }
            $action->finish($state, $config, $context);
        });

        $this->assertSame(2, $state->getFailed());
        $this->assertSame([ExposePublishAction::MISSING_ACCESS], array_unique(array_column($state->getErrors(), 'message')));
        // Never removed on an error
        $this->assertSame(['published.jpg'], array_keys($this->published()));
    }

    public function testARefusedAccessFailsTheRemainingAssets(): void
    {
        $this->api->failures['POST /uploads'] = 401;

        $state = $this->publish($this->config(), [[[$this->asset('a.jpg'), $this->asset('b.jpg')]], [[$this->asset('c.jpg')]]]);

        $this->assertSame(3, $state->getFailed());
        $this->assertSame([ExposePublishAction::MISSING_ACCESS], array_unique(array_column($state->getErrors(), 'message')));
        $this->assertSame(1, count(array_filter($this->api->requests, fn (string $r): bool => 'POST /uploads' === $r)));
    }

    public function testAnAssetErrorOnlyFailsThisAsset(): void
    {
        $this->api->failures['POST /assets'] = 500;

        $state = $this->publish($this->config(), [[[$this->asset('a.jpg'), $this->asset('b.jpg')]]]);

        $this->assertSame(2, $state->getFailed());
        $this->assertSame(2, count(array_filter($this->api->requests, fn (string $r): bool => 'POST /assets' === $r)));
    }

    public function testUnreadableAssetsAreSkipped(): void
    {
        $otherWorkspace = $this->createWorkspace(['ownerId' => 'someone-else', 'name' => 'Other', 'slug' => 'other']);
        $private = $this->createPublishableAsset($otherWorkspace, 'someone-else', 'private.jpg');
        $withoutSource = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);

        $state = $this->publish($this->config(), [[[$private, $withoutSource]]]);

        $this->assertSame(2, $state->getSkipped());
        $this->assertSame(['Not allowed to read the asset', 'No file to publish'], array_column($state->getErrors(), 'message'));
        $this->assertSame([], $this->published());
    }

    private function assertViolation(string $path, callable $fn, ?string $message = null): void
    {
        try {
            $fn();
            $this->fail('A validation exception was expected');
        } catch (ValidationException $e) {
            $violations = $e->getConstraintViolationList();
            $this->assertSame($path, $violations->get(0)->getPropertyPath(), (string) $violations);
            if (null !== $message) {
                $this->assertSame($message, $violations->get(0)->getMessage());
            }
        }
    }
}
