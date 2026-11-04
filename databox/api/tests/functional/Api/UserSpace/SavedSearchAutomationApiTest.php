<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Automation\Action\TagsAction;
use App\Consumer\Handler\Automation\ResetAutomationCursor;
use App\Consumer\Handler\Automation\RunAutomation;
use App\Entity\Automation\AutomationRun;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Tag;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\AutomationScopeEnum;
use App\Model\SavedSearchPrivacyEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class SavedSearchAutomationApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    private function createSavedSearch(string $ownerId = self::USER): SavedSearch
    {
        $em = self::getEntityManager();
        $search = new SavedSearch();
        $search->setName('Red cats');
        $search->setOwnerId($ownerId);
        $search->setPrivacy(SavedSearchPrivacyEnum::Secret);
        $search->setData(['query' => 'cats']);
        $em->persist($search);
        $em->flush();

        return $search;
    }

    private function createTag(): Tag
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);

        return $this->findOrCreateTagByName('Done', $workspace);
    }

    private function payload(SavedSearch $savedSearch, Tag $tag, array $overrides = []): array
    {
        return array_merge([
            'savedSearch' => $savedSearch->getId(),
            'name' => 'Tag new cats',
            'schedule' => '*/5 * * * *',
            'timezone' => 'Europe/Paris',
            'scope' => 'new',
            'actions' => [
                ['type' => TagsAction::TYPE, 'config' => ['add' => [$tag->getId()]]],
            ],
        ], $overrides);
    }

    private function createAutomation(SavedSearch $savedSearch, Tag $tag, array $overrides = []): array
    {
        return $this->apiJson('POST', '/saved-search-automations', self::USER, $this->payload($savedSearch, $tag, $overrides));
    }

    public function testCreate(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();

        $data = $this->createAutomation($savedSearch, $tag);

        $this->assertSame('saved-search-automation', $data['@type']);
        $this->assertSame($savedSearch->getId(), $data['savedSearchId']);
        $this->assertSame('Tag new cats', $data['name']);
        $this->assertTrue($data['enabled']);
        $this->assertSame('*/5 * * * *', $data['schedule']);
        $this->assertSame('Europe/Paris', $data['timezone']);
        $this->assertSame('new', $data['scope']);
        $this->assertSame([
            ['type' => TagsAction::TYPE, 'config' => ['add' => [$tag->getId()], 'remove' => []]],
        ], $data['actions']);
        $this->assertSame(['edit' => true, 'delete' => true, 'run' => true], $data['capabilities']);
        $this->assertSame(self::USER, $data['createdBy']['id']);

        $nextRunAt = new \DateTimeImmutable($data['nextRunAt']);
        $this->assertGreaterThan(new \DateTimeImmutable(), $nextRunAt);
        $this->assertLessThanOrEqual(new \DateTimeImmutable('+5 minutes'), $nextRunAt);
        $this->assertSame(0, ((int) $nextRunAt->format('i')) % 5);

        $list = $this->apiJson('GET', '/saved-search-automations', self::USER, query: ['savedSearch' => $savedSearch->getId()]);
        $this->assertSame([$data['id']], $this->memberIds($list));

        // The capability is exposed on the saved search
        $this->assertTrue($this->apiJson('GET', '/saved-searches/'.$savedSearch->getId(), self::USER)['capabilities']['manageAutomations']);
    }

    public function testUpdateRecomputesNextRun(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();
        $id = $this->createAutomation($savedSearch, $tag)['id'];
        $uri = '/saved-search-automations/'.$id;

        // On demand only
        $data = $this->apiJson('PATCH', $uri, self::USER, ['schedule' => '']);
        $this->assertNull($data['schedule'] ?? null);
        $this->assertNull($data['nextRunAt'] ?? null);
        // Omitted fields are kept
        $this->assertSame('Tag new cats', $data['name']);
        $this->assertCount(1, $data['actions']);

        $data = $this->apiJson('PATCH', $uri, self::USER, ['schedule' => '0 8 * * *', 'enabled' => false]);
        $this->assertSame('0 8 * * *', $data['schedule']);
        $this->assertNull($data['nextRunAt'] ?? null);

        $data = $this->apiJson('PATCH', $uri, self::USER, ['enabled' => true, 'scope' => 'all', 'name' => 'Renamed']);
        $this->assertNotNull($data['nextRunAt']);
        $this->assertSame('all', $data['scope']);
        $this->assertSame('Renamed', $data['name']);

        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);
    }

    #[DataProvider('getInvalidPayloads')]
    public function testValidation(array $overrides, string $expectedPath): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();

        $response = $this->api('POST', '/saved-search-automations', self::USER, $this->payload($savedSearch, $tag, $overrides));
        $this->assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertContains($expectedPath, array_column($response->toArray(false)['violations'] ?? [], 'propertyPath'));
    }

    public static function getInvalidPayloads(): array
    {
        return [
            'blank name' => [['name' => ''], 'name'],
            'invalid cron' => [['schedule' => 'every day'], 'schedule'],
            'too frequent' => [['schedule' => '* * * * *'], 'schedule'],
            'invalid timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
            'invalid scope' => [['scope' => 'some'], 'scope'],
            'no action' => [['actions' => []], 'actions'],
            'unknown action' => [['actions' => [['type' => 'teleport', 'config' => []]]], 'actions[0].type'],
            'invalid action config' => [['actions' => [['type' => TagsAction::TYPE, 'config' => ['add' => []]]]], 'actions[0].config.add'],
            'unknown tag' => [['actions' => [['type' => TagsAction::TYPE, 'config' => ['add' => ['00000000-0000-4000-8000-000000000000']]]]], 'actions[0].config.add'],
        ];
    }

    public function testOnlyTheOwnerOfTheSavedSearchManagesItsAutomations(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();
        // An editor of the saved search must not act with the rights of its owner
        $this->grantUserOnObject(self::OTHER, $savedSearch, PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::DELETE | PermissionInterface::OWNER);
        $this->assertFalse($this->apiJson('GET', '/saved-searches/'.$savedSearch->getId(), self::OTHER)['capabilities']['manageAutomations']);

        $this->assertStatus(403, 'POST', '/saved-search-automations', self::OTHER, $this->payload($savedSearch, $tag));
        $this->assertStatus(403, 'GET', '/saved-search-automations', self::OTHER, query: ['savedSearch' => $savedSearch->getId()]);
        $this->assertStatus(401, 'GET', '/saved-search-automations', null, query: ['savedSearch' => $savedSearch->getId()]);

        $id = $this->createAutomation($savedSearch, $tag)['id'];
        $uri = '/saved-search-automations/'.$id;
        $this->assertStatus(403, 'GET', $uri, self::OTHER);
        $this->assertStatus(403, 'PATCH', $uri, self::OTHER, ['name' => 'Hijack']);
        $this->assertStatus(403, 'POST', $uri.'/run', self::OTHER, ['dryRun' => false]);
        $this->assertStatus(403, 'POST', $uri.'/reset-cursor', self::OTHER, ['mode' => 'empty']);
        $this->assertStatus(403, 'GET', '/automation-runs', self::OTHER, query: ['automation' => $id]);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);

        // Admins manage every automation
        $this->assertSame('By admin', $this->apiJson('PATCH', $uri, self::ADMIN, ['name' => 'By admin'])['name']);
    }

    public function testUnknownResources(): void
    {
        $this->assertStatus(404, 'GET', '/saved-search-automations', self::USER, query: ['savedSearch' => '00000000-0000-4000-8000-000000000000']);
        $this->assertStatus(404, 'POST', '/saved-search-automations/00000000-0000-4000-8000-000000000000/run', self::USER, ['dryRun' => true]);
        $this->assertStatus(404, 'GET', '/automation-runs', self::USER, query: ['automation' => '00000000-0000-4000-8000-000000000000']);
    }

    public function testRunCreatesAPendingRun(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();
        $id = $this->createAutomation($savedSearch, $tag)['id'];

        $client = static::createClient();
        $client->disableReboot();
        $task = $this->interceptTransport('task');

        $response = $client->request('POST', '/saved-search-automations/'.$id.'/run', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['dryRun' => true],
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame('automation-run', $data['@type']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('manual', $data['trigger']);
        $this->assertTrue($data['dryRun']);
        $this->assertSame(self::USER, $data['initiator']['id']);
        $this->assertSame($id, $data['automationId']);

        $messages = array_values(array_filter(
            array_map(fn ($envelope): object => $envelope->getMessage(), $task->getSent()),
            fn (object $message): bool => $message instanceof RunAutomation,
        ));
        $this->assertCount(1, $messages);
        $this->assertSame($data['id'], $messages[0]->runId);

        $response = $client->request('GET', '/automation-runs', [
            'headers' => self::authHeaders(self::USER),
            'query' => ['automation' => $id],
        ]);
        $this->assertSame([$data['id']], array_column($response->toArray()['member'], 'id'));

        $run = self::getEntityManager()->find(AutomationRun::class, $data['id']);
        $this->assertInstanceOf(AutomationRun::class, $run);
    }

    public function testResetCursor(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();
        $id = $this->createAutomation($savedSearch, $tag)['id'];

        $client = static::createClient();
        $client->disableReboot();
        $task = $this->interceptTransport('task');

        $response = $client->request('POST', '/saved-search-automations/'.$id.'/reset-cursor', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['mode' => 'current'],
        ]);
        $this->assertSame(204, $response->getStatusCode(), $response->getContent(false));
        $sent = array_map(fn ($envelope): object => $envelope->getMessage(), $task->getSent());
        $this->assertCount(1, $sent);
        $this->assertInstanceOf(ResetAutomationCursor::class, $sent[0]);

        $response = $client->request('POST', '/saved-search-automations/'.$id.'/reset-cursor', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['mode' => 'empty'],
        ]);
        $this->assertSame(204, $response->getStatusCode(), $response->getContent(false));

        $response = $client->request('POST', '/saved-search-automations/'.$id.'/reset-cursor', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['mode' => 'whatever'],
        ]);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent(false));
    }

    public function testOwnerChangeDisablesTheAutomations(): void
    {
        $savedSearch = $this->createSavedSearch();
        $tag = $this->createTag();
        $id = $this->createAutomation($savedSearch, $tag)['id'];

        $em = self::getEntityManager();
        $savedSearch = $em->find(SavedSearch::class, $savedSearch->getId());
        $savedSearch->setOwnerId(self::OTHER);
        $em->flush();
        $em->clear();

        $automation = $em->find(SavedSearchAutomation::class, $id);
        $this->assertFalse($automation->isEnabled());
        $this->assertNull($automation->getNextRunAt());
        $this->assertSame(AutomationScopeEnum::New, $automation->getScope());
    }

    private function interceptTransport(string $name): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.'.$name);

        return $transport->intercept();
    }
}
