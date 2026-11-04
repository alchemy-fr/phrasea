<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Attribute\Type\TextAttributeType;
use App\Automation\Action\AddToCollectionAction;
use App\Automation\Action\AttributeAction;
use App\Automation\Action\AutomationActionInterface;
use App\Automation\Action\MoveToCollectionAction;
use App\Automation\Action\TagsAction;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Model\AutomationTriggerEnum;
use App\Service\Workspace\WorkspaceCreator;
use App\Tests\Functional\AbstractDataboxTestCase;

final class AutomationActionsTest extends AbstractDataboxTestCase
{
    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
    }

    /**
     * @param Asset[] $assets
     */
    private function process(string $actionClass, array $config, array $assets, string $userId = self::OWNER): AutomationActionState
    {
        /** @var AutomationActionInterface $action */
        $action = self::getService($actionClass);
        $state = new AutomationActionState($actionClass::TYPE);
        $em = self::getEntityManager();
        $em->clear();
        $assets = array_map(fn (Asset $asset): Asset => $em->find(Asset::class, $asset->getId()), $assets);

        self::getService(UserContextSwitcher::class)->runAs($userId, function () use ($action, $config, $assets, $state): void {
            $state->beginBatch();
            $action->processBatch(new AutomationBatch($assets, $action->validateConfig($config), $state, new AutomationRunContext(
                runId: 'run',
                automationId: 'automation',
                automationName: 'Test',
                savedSearchId: 'search',
                savedSearchName: 'Search',
                ownerId: self::OWNER,
                trigger: AutomationTriggerEnum::Manual,
                dryRun: false,
            )));
        });
        $em->clear();

        return $state;
    }

    private function validate(string $actionClass, array $config, string $userId = self::OWNER): array
    {
        /** @var AutomationActionInterface $action */
        $action = self::getService($actionClass);

        return self::getService(UserContextSwitcher::class)->runAs($userId, fn (): array => $action->validateConfig($config));
    }

    private function createOwnedAsset(string $name, ?Collection $collection = null, ?Workspace $workspace = null): Asset
    {
        return $this->createAsset([
            'workspace' => $workspace ?? $this->workspace,
            'ownerId' => self::OWNER,
            'name' => $name,
            'collectionId' => $collection?->getId(),
        ]);
    }

    private function createSecondWorkspace(): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName('Other');
        $workspace->setSlug('other-ws');
        $workspace->setOwnerId(self::OWNER);
        $workspace->setEnabledLocales(['en']);
        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        self::getEntityManager()->flush();

        return $workspace;
    }

    private function getCollectionIds(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();
        $ids = array_map(
            fn (CollectionAsset $ca): string => $ca->getCollection()->getId(),
            $em->getRepository(CollectionAsset::class)->findBy(['asset' => $assetId]),
        );
        sort($ids);

        return $ids;
    }

    public function testMoveToCollection(): void
    {
        $from = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'From']);
        $to = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'To']);
        $moved = $this->createOwnedAsset('Moved', $from);
        $alreadyThere = $this->createOwnedAsset('Already there', $to);
        $otherWorkspace = $this->createOwnedAsset('Elsewhere', workspace: $this->createSecondWorkspace());

        $state = $this->process(MoveToCollectionAction::class, ['collectionId' => '/collections/'.$to->getId()], [$moved, $alreadyThere, $otherWorkspace]);

        $this->assertSame(2, $state->getProcessed());
        $this->assertSame(1, $state->getSkipped());
        $this->assertSame(0, $state->getFailed());
        $this->assertSame([['assetId' => $otherWorkspace->getId(), 'message' => 'The asset cannot be moved to another workspace']], $state->getErrors());
        // AssetMove is handled synchronously in the test environment
        $this->assertSame([$to->getId()], $this->getCollectionIds($moved->getId()));
        $this->assertSame([$to->getId()], $this->getCollectionIds($alreadyThere->getId()));
    }

    public function testMoveValidationChecksThePermissions(): void
    {
        $to = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'To']);

        $this->expectException(ValidationException::class);
        $this->validate(MoveToCollectionAction::class, ['collectionId' => $to->getId()], self::OTHER);
    }

    public function testMoveRequiresAnExistingCollection(): void
    {
        try {
            $this->validate(MoveToCollectionAction::class, ['collectionId' => 'not-an-id']);
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertSame('collectionId', $e->getConstraintViolationList()->get(0)->getPropertyPath());
        }
    }

    public function testAddToCollection(): void
    {
        $from = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'From']);
        $to = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'To']);
        $asset = $this->createOwnedAsset('Asset', $from);
        $alreadyIn = $this->createOwnedAsset('Already in', $to);

        $state = $this->process(AddToCollectionAction::class, ['collectionId' => $to->getId()], [$asset, $alreadyIn]);
        $this->assertSame(2, $state->getProcessed());
        $this->assertSame(0, $state->getSkipped());

        $expected = [$from->getId(), $to->getId()];
        sort($expected);
        $this->assertSame($expected, $this->getCollectionIds($asset->getId()));
        $this->assertSame([$to->getId()], $this->getCollectionIds($alreadyIn->getId()));

        // Idempotent
        $state = $this->process(AddToCollectionAction::class, ['collectionId' => $to->getId()], [$asset]);
        $this->assertSame(1, $state->getProcessed());
        $this->assertSame($expected, $this->getCollectionIds($asset->getId()));
    }

    public function testAddToCollectionIsSkippedWithoutPermission(): void
    {
        $to = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'To']);
        $asset = $this->createOwnedAsset('Asset');

        // The configuration was valid for its author, the rights changed since
        $config = $this->validate(AddToCollectionAction::class, ['collectionId' => $to->getId()]);
        $state = new AutomationActionState(AddToCollectionAction::TYPE);
        $em = self::getEntityManager();
        $asset = $em->find(Asset::class, $asset->getId());
        self::getService(UserContextSwitcher::class)->runAs(self::OTHER, function () use ($config, $asset, $state): void {
            $state->beginBatch();
            self::getService(AddToCollectionAction::class)->processBatch(new AutomationBatch([$asset], $config, $state, new AutomationRunContext(
                runId: 'run',
                automationId: 'automation',
                automationName: 'Test',
                savedSearchId: 'search',
                savedSearchName: null,
                ownerId: self::OTHER,
                trigger: AutomationTriggerEnum::Schedule,
                dryRun: false,
            )));
        });

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(1, $state->getSkipped());
        $this->assertSame(0, $state->getFailed());
        $this->assertSame([], $this->getCollectionIds($asset->getId()));
    }

    public function testAttribute(): void
    {
        $definition = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Status',
            'type' => TextAttributeType::NAME,
        ]);
        $asset = $this->createOwnedAsset('Asset');
        $otherWorkspace = $this->createOwnedAsset('Elsewhere', workspace: $this->createSecondWorkspace());

        $state = $this->process(AttributeAction::class, [
            'definitionId' => $definition->getId(),
            'action' => 'set',
            'value' => 'Archived',
        ], [$asset, $otherWorkspace]);

        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(1, $state->getSkipped());

        $em = self::getEntityManager();
        $attributes = $em->getRepository(Attribute::class)->findBy(['asset' => $asset->getId(), 'definition' => $definition->getId()]);
        $this->assertCount(1, $attributes);
        $this->assertSame('Archived', $attributes[0]->getValue());
        $this->assertSame([], $em->getRepository(Attribute::class)->findBy(['asset' => $otherWorkspace->getId(), 'definition' => $definition->getId()]));

        // Idempotent
        $state = $this->process(AttributeAction::class, [
            'definitionId' => $definition->getId(),
            'action' => 'set',
            'value' => 'Archived',
        ], [$asset]);
        $this->assertSame(1, $state->getProcessed());
        $this->assertCount(1, $em->getRepository(Attribute::class)->findBy(['asset' => $asset->getId(), 'definition' => $definition->getId()]));
    }

    public function testAttributeValidation(): void
    {
        $definition = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Status',
            'type' => TextAttributeType::NAME,
        ]);

        $this->assertSame([
            'definitionId' => $definition->getId(),
            'action' => 'delete',
            'locale' => null,
        ], $this->validate(AttributeAction::class, ['definitionId' => $definition->getId(), 'action' => 'delete']));

        foreach ([
            'unknown definition' => [['definitionId' => '00000000-0000-4000-8000-000000000000'], 'definitionId'],
            'unknown action' => [['definitionId' => $definition->getId(), 'action' => 'explode'], 'action'],
            'add on a single value' => [['definitionId' => $definition->getId(), 'action' => 'add', 'value' => 'x'], 'action'],
            'replace without text' => [['definitionId' => $definition->getId(), 'action' => 'replace'], 'value'],
        ] as $case => [$config, $path]) {
            try {
                $this->validate(AttributeAction::class, $config);
                $this->fail(sprintf('Expected a validation error for "%s"', $case));
            } catch (ValidationException $e) {
                $this->assertSame($path, $e->getConstraintViolationList()->get(0)->getPropertyPath(), $case);
            }
        }
    }

    public function testTags(): void
    {
        $keep = $this->findOrCreateTagByName('Keep', $this->workspace);
        $add = $this->findOrCreateTagByName('Add', $this->workspace);
        $remove = $this->findOrCreateTagByName('Remove', $this->workspace);
        $asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER,
            'name' => 'Tagged',
            'tags' => [$keep->getId(), $remove->getId()],
        ]);
        $otherWorkspace = $this->createOwnedAsset('Elsewhere', workspace: $this->createSecondWorkspace());

        $config = ['add' => [$add->getId()], 'remove' => [$remove->getId()]];
        $state = $this->process(TagsAction::class, $config, [$asset, $otherWorkspace]);
        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(1, $state->getSkipped());

        $tagNames = fn (): array => array_values(array_map(
            fn ($tag): string => $tag->getName(),
            self::getEntityManager()->find(Asset::class, $asset->getId())->getTags()->toArray(),
        ));
        $names = $tagNames();
        sort($names);
        $this->assertSame(['Add', 'Keep'], $names);

        // Idempotent
        $this->assertSame(1, $this->process(TagsAction::class, $config, [$asset])->getProcessed());
        $names = $tagNames();
        sort($names);
        $this->assertSame(['Add', 'Keep'], $names);
    }

    public function testTagsAreSkippedWithoutPermission(): void
    {
        $tag = $this->findOrCreateTagByName('Add', $this->workspace);
        $asset = $this->createOwnedAsset('Asset');
        $config = $this->validate(TagsAction::class, ['add' => [$tag->getId()]]);

        $state = new AutomationActionState(TagsAction::TYPE);
        $asset = self::getEntityManager()->find(Asset::class, $asset->getId());
        self::getService(UserContextSwitcher::class)->runAs(self::OTHER, function () use ($config, $asset, $state): void {
            $state->beginBatch();
            self::getService(TagsAction::class)->processBatch(new AutomationBatch([$asset], $config, $state, new AutomationRunContext(
                runId: 'run',
                automationId: 'automation',
                automationName: 'Test',
                savedSearchId: 'search',
                savedSearchName: null,
                ownerId: self::OTHER,
                trigger: AutomationTriggerEnum::Schedule,
                dryRun: false,
            )));
        });

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(1, $state->getSkipped());
        $this->assertCount(0, self::getEntityManager()->find(Asset::class, $asset->getId())->getTags());
    }
}
