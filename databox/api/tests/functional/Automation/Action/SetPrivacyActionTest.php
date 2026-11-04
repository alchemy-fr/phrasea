<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Alchemy\ESBundle\Message\ESIndex;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\Action\SetPrivacyAction;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Automation\AutomationTestTrait;
use Symfony\Component\Messenger\Envelope;

final class SetPrivacyActionTest extends AbstractDataboxTestCase
{
    use AutomationActionTestTrait;
    use AutomationTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
    }

    private function getPrivacy(string $assetId): int
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Asset::class, $assetId)->getPrivacy();
    }

    /**
     * @return string[] the IDs of the assets scheduled for indexing
     */
    private static function getIndexedAssetIds(array $envelopes): array
    {
        $ids = [];
        foreach ($envelopes as $envelope) {
            /** @var Envelope $envelope */
            $message = $envelope->getMessage();
            if ($message instanceof ESIndex) {
                foreach ($message->getObjects()[Asset::class] ?? [] as $operationIds) {
                    $ids = array_merge($ids, $operationIds);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function testSetPrivacy(): void
    {
        $asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);
        $unchanged = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'no_flush' => true]);
        $unchanged->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        self::getEntityManager()->flush();

        self::getService(DeferredIndexListener::class)->flush();
        $transport = self::interceptTransport('p1');
        $state = $this->processAction(
            self::getService(SetPrivacyAction::class),
            ['privacy' => WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE],
            [$asset, $unchanged],
        );

        $this->assertSame(2, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertSame(0, $state->getSkipped());
        $this->assertSame(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $this->getPrivacy($asset->getId()));
        $this->assertSame(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $this->getPrivacy($unchanged->getId()));
        // Reindexed once the worker message is handled
        self::getService(DeferredIndexListener::class)->flush();
        $this->assertSame([$asset->getId()], self::getIndexedAssetIds($transport->getSent()));

        // Idempotent
        $state = $this->processAction(
            self::getService(SetPrivacyAction::class),
            ['privacy' => 'public_in_workspace'],
            [$asset],
        );
        $this->assertSame(1, $state->getProcessed());
        $this->assertSame(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $this->getPrivacy($asset->getId()));
    }

    public function testSetPrivacyIsSkippedWithoutPermission(): void
    {
        $notEditable = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);
        // Its owner may edit it, but not its permissions
        $permissionsNotEditable = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OTHER]);

        $state = $this->processAction(
            self::getService(SetPrivacyAction::class),
            ['privacy' => WorkspaceItemPrivacyInterface::PUBLIC],
            [$notEditable, $permissionsNotEditable],
            self::OTHER,
        );

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(2, $state->getSkipped());
        $this->assertSame(0, $state->getFailed());
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $this->getPrivacy($notEditable->getId()));
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $this->getPrivacy($permissionsNotEditable->getId()));
    }

    public function testSetPrivacyValidation(): void
    {
        $action = self::getService(SetPrivacyAction::class);
        $this->assertSame(['privacy' => WorkspaceItemPrivacyInterface::PRIVATE], $action->validateConfig(['privacy' => 'private']));
        $this->assertSame(['privacy' => WorkspaceItemPrivacyInterface::SECRET], $action->validateConfig(['privacy' => 0]));

        foreach ([[], ['privacy' => 6], ['privacy' => 'everyone'], ['privacy' => '2']] as $config) {
            try {
                $action->validateConfig($config);
                $this->fail(sprintf('Expected a validation error for %s', json_encode($config)));
            } catch (ValidationException $e) {
                $this->assertSame('privacy', $e->getConstraintViolationList()->get(0)->getPropertyPath());
            }
        }
    }
}
