<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use Alchemy\NotifierBundle\Message\SendNotification;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\Action\EmailReportAction;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Automation\AutomationTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class EmailReportActionTest extends AbstractSearchTestCase
{
    use AutomationTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
    }

    private function getAction(): EmailReportAction
    {
        return self::getService(EmailReportAction::class);
    }

    private function validate(array $config): array
    {
        $action = $this->getAction();

        return self::getService(UserContextSwitcher::class)->runAs(self::OWNER, fn (): array => $action->validateConfig($config));
    }

    private function createContext(AutomationScopeEnum $scope = AutomationScopeEnum::New): AutomationRunContext
    {
        return new AutomationRunContext(
            runId: 'run',
            automationId: 'automation',
            automationName: 'Daily report',
            savedSearchId: 'search',
            savedSearchName: 'My search',
            ownerId: self::OWNER,
            trigger: AutomationTriggerEnum::Manual,
            dryRun: false,
            scope: $scope,
        );
    }

    /**
     * @param list<Asset[]> $batches
     */
    private function processBatches(array $config, array $batches): AutomationActionState
    {
        $action = $this->getAction();
        $state = new AutomationActionState(EmailReportAction::TYPE);
        $em = self::getEntityManager();
        $em->clear();

        foreach ($batches as $assets) {
            $assets = array_map(fn (Asset $asset): Asset => $em->find(Asset::class, $asset->getId()), $assets);
            $state->beginBatch();
            $action->processBatch(new AutomationBatch($assets, $config, $state, $this->createContext()));
        }

        return $state;
    }

    private function createOwnedAsset(string $name, ?Collection $collection = null): Asset
    {
        return $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER,
            'name' => $name,
            'collectionId' => $collection?->getId(),
        ]);
    }

    private static function interceptNotifications(): InMemoryTransport
    {
        $transport = self::interceptTransport('p2');
        $transport->reset();

        return $transport;
    }

    /**
     * @return array<SendNotification|SendEmailNotification>
     */
    private static function getMessages(InMemoryTransport $transport): array
    {
        return array_values(array_filter(
            array_map(fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            fn (object $message): bool => $message instanceof SendNotification || $message instanceof SendEmailNotification,
        ));
    }

    public function testValidateConfig(): void
    {
        $this->assertSame([
            'users' => [self::OTHER],
            'groups' => [KeycloakClientTestMock::GROUP_UID],
            'emails' => ['John@example.com'],
            'sendWhenEmpty' => false,
            'maxListed' => EmailReportAction::DEFAULT_MAX_LISTED,
            'subject' => null,
        ], $this->validate([
            'users' => [self::OTHER, self::OTHER],
            'groups' => [KeycloakClientTestMock::GROUP_UID],
            'emails' => [' John@example.com ', 'john@example.com'],
            'subject' => '  ',
        ]));

        $this->assertSame([
            'users' => [],
            'groups' => [],
            'emails' => ['a@example.com'],
            'sendWhenEmpty' => true,
            'maxListed' => 200,
            'subject' => 'New photos',
        ], $this->validate([
            'emails' => ['a@example.com'],
            'sendWhenEmpty' => true,
            'maxListed' => 200,
            'subject' => ' New photos ',
        ]));
    }

    public static function getInvalidConfigs(): array
    {
        $tooMany = [];
        for ($i = 0; $i <= EmailReportAction::MAX_RECIPIENTS; ++$i) {
            $tooMany[] = sprintf('user%d@example.com', $i);
        }

        return [
            'no recipient' => [[], 'recipients'],
            'empty lists' => [['users' => [], 'groups' => [], 'emails' => []], 'recipients'],
            'too many recipients' => [['emails' => $tooMany], 'recipients'],
            'invalid email' => [['emails' => ['not an email']], 'emails'],
            'emails not a list' => [['emails' => 'a@example.com'], 'emails'],
            'maxListed too high' => [['emails' => ['a@example.com'], 'maxListed' => 201], 'maxListed'],
            'maxListed too low' => [['emails' => ['a@example.com'], 'maxListed' => 0], 'maxListed'],
            'sendWhenEmpty not a boolean' => [['emails' => ['a@example.com'], 'sendWhenEmpty' => 'yes'], 'sendWhenEmpty'],
            'subject too long' => [['emails' => ['a@example.com'], 'subject' => str_repeat('a', 256)], 'subject'],
        ];
    }

    #[DataProvider('getInvalidConfigs')]
    public function testInvalidConfig(array $config, string $path): void
    {
        try {
            $this->validate($config);
            $this->fail('A validation error was expected');
        } catch (ValidationException $e) {
            $this->assertSame($path, $e->getConstraintViolationList()->get(0)->getPropertyPath());
        }
    }

    public function testAccumulatesUpToMaxListed(): void
    {
        $a1 = $this->createOwnedAsset('First');
        $a2 = $this->createOwnedAsset('Second');
        $a3 = $this->createOwnedAsset('Third');

        $config = $this->validate(['emails' => ['a@example.com'], 'maxListed' => 2]);
        $state = $this->processBatches($config, [[$a1, $a2], [$a3]]);

        $this->assertSame(3, $state->getProcessed());
        $this->assertSame(0, $state->getFailed());
        $this->assertSame(3, $state->data['total']);
        $this->assertSame([$a1->getId(), $a2->getId()], array_column($state->data['assets'], 'id'));
        $this->assertSame(['First', 'Second'], array_column($state->data['assets'], 'title'));
        $this->assertSame($this->workspace->getName(), $state->data['assets'][0]['workspace']);
        $this->assertNotNull($state->data['assets'][0]['createdAt']);
    }

    public function testSendsToUsersGroupMembersAndEmails(): void
    {
        $asset = $this->createOwnedAsset('First');
        $config = $this->validate([
            'users' => [self::OWNER],
            'groups' => [KeycloakClientTestMock::GROUP_UID],
            'emails' => ['a@example.com', 'b@example.com'],
            'subject' => 'Report',
        ]);
        $state = $this->processBatches($config, [[$asset]]);

        $transport = self::interceptNotifications();
        $this->getAction()->finish($state, $config, $this->createContext());
        $messages = self::getMessages($transport);

        $notifications = array_values(array_filter($messages, fn (object $m): bool => $m instanceof SendNotification));
        $this->assertCount(1, $notifications);
        $selector = $notifications[0]->selectors[0];
        // The disabled member of the group is left out
        $this->assertSame([self::OWNER, self::OTHER], $selector->userIds);
        $this->assertSame(EmailReportAction::TOPIC, $selector->topic->topic);
        $params = $selector->topic->params;
        $this->assertSame(1, $params['count']);
        $this->assertSame(0, $params['more']);
        $this->assertSame('Report', $params['customSubject']);
        $this->assertSame('Daily report', $params['automationName']);
        $this->assertSame('new', $params['scope']);
        $this->assertSame('/saved-searches/search', $params['url']);
        $this->assertSame([$asset->getId()], array_column($params['assets'], 'id'));

        $emails = array_values(array_filter($messages, fn (object $m): bool => $m instanceof SendEmailNotification));
        $this->assertSame(['a@example.com', 'b@example.com'], array_map(fn (SendEmailNotification $m): string => $m->email, $emails));
        $this->assertSame(EmailReportAction::TOPIC, $emails[0]->topic);
        $this->assertSame($params, $emails[0]->params);
    }

    public function testNothingSentWhenEmpty(): void
    {
        $config = $this->validate(['users' => [self::OWNER], 'emails' => ['a@example.com']]);
        $transport = self::interceptNotifications();
        $this->getAction()->finish(new AutomationActionState(EmailReportAction::TYPE), $config, $this->createContext());
        $this->assertSame([], self::getMessages($transport));

        $config = $this->validate(['users' => [self::OWNER], 'emails' => ['a@example.com'], 'sendWhenEmpty' => true]);
        $this->getAction()->finish(new AutomationActionState(EmailReportAction::TYPE), $config, $this->createContext());
        $messages = self::getMessages($transport);
        $this->assertCount(2, $messages);
        $this->assertSame(0, $messages[0]->selectors[0]->topic->params['count']);
    }

    public function testRunSendsTheNewAssets(): void
    {
        $source = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'name' => 'Source']);
        $first = $this->createOwnedAsset('First', $source);
        $this->createOwnedAsset('Out');
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $savedSearch = $this->createSavedSearchWith(self::OWNER, [
            'conditions' => [sprintf('@collection = "%s"', $source->getId())],
        ]);
        $automation = $this->createAutomationEntity($savedSearch, [
            ['type' => EmailReportAction::TYPE, 'config' => ['users' => [], 'groups' => [], 'emails' => ['a@example.com'], 'sendWhenEmpty' => false, 'maxListed' => 50, 'subject' => null]],
        ]);
        $transport = self::interceptNotifications();

        // A dry run sends nothing
        $run = $this->runAutomation($automation->getId(), dryRun: true);
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus());
        $this->assertSame([], self::getMessages($transport));

        $run = $this->runAutomation($automation->getId());
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus());
        $this->assertSame('success', $run->getActionResults()[0]['status']);
        $messages = self::getMessages($transport);
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(SendEmailNotification::class, $messages[0]);
        $this->assertSame('a@example.com', $messages[0]->email);
        $this->assertSame(1, $messages[0]->params['count']);
        $this->assertSame([$first->getId()], array_column($messages[0]->params['assets'], 'id'));
        $this->assertSame('Automated search', $messages[0]->params['savedSearchName']);
        $this->assertSame('/saved-searches/'.$savedSearch->getId(), $messages[0]->params['url']);

        // Nothing new: no report
        $run = $this->runAutomation($automation->getId());
        $this->assertSame(AutomationRunStatusEnum::Success, $run->getStatus());
        $this->assertCount(1, self::getMessages($transport));
    }
}
