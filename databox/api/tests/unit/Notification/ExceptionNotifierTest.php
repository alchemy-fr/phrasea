<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationRegistry;
use App\Integration\RemoveBg\RemoveBgIntegration;
use App\Notification\EntityDisableNotifyableException;
use App\Notification\ExceptionNotifier;
use App\Notification\UserNotifyableException;
use App\Service\ErrorDisableHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExceptionNotifierTest extends TestCase
{
    private const string CLIENT_URL = 'https://databox.example.com/';

    /**
     * @var SendNotification[]
     */
    private array $messages = [];

    public function testIntegrationOfAWorkspace(): void
    {
        $workspace = new Workspace();
        $workspace->setName('alchemy');
        $integration = $this->createIntegration($workspace);

        $payload = $this->notify(new EntityDisableNotifyableException($integration, 'Integration "alchemy rnd" error', 'Invalid timeout'));

        $this->assertSame('Integration "alchemy rnd" error', $payload['subject']);
        $this->assertSame('Invalid timeout (in workspace: alchemy)', $payload['message']);
        $this->assertSame([
            ['key' => 'instance', 'value' => 'https://databox.example.com'],
            ['key' => 'workspace', 'value' => 'alchemy'],
            ['key' => 'integration', 'value' => 'alchemy rnd'],
            ['key' => 'integration_type', 'value' => RemoveBgIntegration::getDisplayName()],
        ], $payload['details']);
        $this->assertSame(sprintf('/workspaces/%s/manage/integrations/%s', $workspace->getId(), $integration->getId()), $payload['url']);
        $this->assertSame('edit_integration', $payload['action']);
    }

    public function testIntegrationOfTheInstance(): void
    {
        $integration = $this->createIntegration(null);
        $integration->setName(null);
        $integration->setIntegration('unknown.type');

        $payload = $this->notify(new EntityDisableNotifyableException($integration, 'Integration error', 'Failure'));

        $this->assertSame([
            ['key' => 'instance', 'value' => 'https://databox.example.com'],
            ['key' => 'integration', 'value' => 'unknown.type'],
            ['key' => 'integration_type', 'value' => 'unknown.type'],
        ], $payload['details']);
        $this->assertSame(sprintf('/admin/integrations/%s', $integration->getId()), $payload['url']);
        $this->assertSame('edit_integration', $payload['action']);
    }

    public function testUriSetByTheCallerIsKept(): void
    {
        $exception = new EntityDisableNotifyableException($this->createIntegration(null), 'Integration error', 'Failure');
        $exception->setUri('/somewhere');
        $exception->setAction('somewhere');

        $payload = $this->notify($exception);

        $this->assertSame('/somewhere', $payload['url']);
        $this->assertSame('somewhere', $payload['action']);
    }

    public function testAttributeDefinition(): void
    {
        $workspace = new Workspace();
        $workspace->setName('alchemy');
        $workspace->setOwnerId('owner');
        $definition = new AttributeDefinition();
        $definition->setName('Title');
        $definition->setWorkspace($workspace);

        $payload = $this->notify(new EntityDisableNotifyableException($definition, 'Attribute error', 'Failure'));

        $this->assertSame([
            ['key' => 'instance', 'value' => 'https://databox.example.com'],
            ['key' => 'workspace', 'value' => 'alchemy'],
            ['key' => 'attribute_definition', 'value' => 'Title'],
        ], $payload['details']);
        $this->assertSame(sprintf('/workspaces/%s/manage/attributes/%s', $workspace->getId(), $definition->getId()), $payload['url']);
        $this->assertSame('edit_attribute_definition', $payload['action']);
    }

    public function testPlainException(): void
    {
        $payload = $this->notify(new UserNotifyableException('owner', 'Automation error', 'Failure'));

        $this->assertSame([
            ['key' => 'instance', 'value' => 'https://databox.example.com'],
        ], $payload['details']);
        $this->assertArrayNotHasKey('url', $payload);
        $this->assertArrayNotHasKey('action', $payload);
    }

    public function testWithoutClientUrl(): void
    {
        $payload = $this->notify(new UserNotifyableException('owner', 'Automation error', 'Failure'), '');

        $this->assertSame([], $payload['details']);
    }

    public function testDisabledEntityIsNotNotified(): void
    {
        $integration = $this->createIntegration(null);
        $integration->setEnabled(false);

        $this->createNotifier(self::CLIENT_URL)->notifyException(new EntityDisableNotifyableException($integration, 'Integration error', 'Failure'));

        $this->assertSame([], $this->messages);
    }

    private function createIntegration(?Workspace $workspace): WorkspaceIntegration
    {
        $integration = new WorkspaceIntegration();
        $integration->setName('alchemy rnd');
        $integration->setIntegration(RemoveBgIntegration::getName());
        $integration->setWorkspace($workspace);
        $integration->setOwnerId('owner');

        return $integration;
    }

    private function notify(UserNotifyableException $exception, string $clientUrl = self::CLIENT_URL): array
    {
        $this->createNotifier($clientUrl)->notifyException($exception);

        $this->assertCount(1, $this->messages);
        $selector = $this->messages[0]->selectors[0];
        $this->assertSame(['owner'], $selector->userIds);
        $this->assertSame('user:exception', $selector->topic->topic);

        return $selector->topic->params;
    }

    private function createNotifier(string $clientUrl): ExceptionNotifier
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->messages[] = $message;

            return new Envelope($message);
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $removeBg = new \ReflectionClass(RemoveBgIntegration::class)->newInstanceWithoutConstructor();

        return new ExceptionNotifier(
            new NotifierManager(
                $bus,
                new TopicRegistry(['user:exception' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true]]),
                new NotifierState(true),
                $em,
                new UserDirectoryRegistry([]),
                $this->createStub(Security::class),
            ),
            new ErrorDisableHandler($em, $this->createStub(ManagerRegistry::class)),
            new IntegrationRegistry([RemoveBgIntegration::getName() => $removeBg]),
            $clientUrl,
        );
    }
}
