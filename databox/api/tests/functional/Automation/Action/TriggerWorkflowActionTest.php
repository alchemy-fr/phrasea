<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Automation\Action\TriggerWorkflowAction;
use App\Entity\Core\Workspace;
use App\Entity\Workflow\WorkflowState;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Automation\AutomationTestTrait;

final class TriggerWorkflowActionTest extends AbstractDataboxTestCase
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

    /**
     * @return WorkflowState[]
     */
    private function findWorkflowStates(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->getRepository(WorkflowState::class)->findBy(['asset' => $assetId]);
    }

    public function testTriggerWorkflow(): void
    {
        $asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);
        $this->assertSame([], self::getService(TriggerWorkflowAction::class)->validateConfig(['unexpected' => true]));

        // The jobs of the workflow are not run
        self::interceptTransport('p2');
        $state = $this->processAction(self::getService(TriggerWorkflowAction::class), [], [$asset]);

        $this->assertSame(1, $state->getProcessed(), json_encode($state->getErrors()));
        $states = $this->findWorkflowStates($asset->getId());
        $this->assertCount(1, $states);
        $this->assertSame(self::OWNER, $states[0]->getInitiatorId());

        // Run again at each run
        $this->processAction(self::getService(TriggerWorkflowAction::class), [], [$asset]);
        $this->assertCount(2, $this->findWorkflowStates($asset->getId()));
    }

    public function testTriggerWorkflowIsSkippedWithoutPermission(): void
    {
        $asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);

        self::interceptTransport('p2');
        $state = $this->processAction(self::getService(TriggerWorkflowAction::class), [], [$asset], self::OTHER);

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(1, $state->getSkipped());
        $this->assertSame([['assetId' => $asset->getId(), 'message' => 'Not allowed to edit the asset']], $state->getErrors());
        $this->assertSame([], $this->findWorkflowStates($asset->getId()));
    }
}
