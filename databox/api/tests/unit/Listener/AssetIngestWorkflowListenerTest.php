<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listener;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\WorkflowState;
use App\Listener\AssetIngestWorkflowListener;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Pusher\Pusher;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetIngestWorkflowListenerTest extends TestCase
{
    public function testAnUnreachablePusherHostIsOnlyLogged(): void
    {
        $pusher = $this->createMock(Pusher::class);
        $pusher->method('getSettings')->willReturn(['host' => 'sockudo']);
        $pusher->expects($this->once())
            ->method('trigger')
            ->willThrowException(new ConnectException('Connection timed out', new Request('POST', 'https://sockudo')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $state = new WorkflowState(
            new MemoryStateRepository(),
            AssetIngestWorkflowEvent::WORKFLOW_NAME.':ws',
            new WorkflowEvent('asset_ingest', ['assetId' => 'a1']),
        );
        $state->setStatus(WorkflowState::STATUS_SUCCESS);

        new AssetIngestWorkflowListener(new PusherManager($pusher, $this->createMock(MessageBusInterface::class)), $logger)
            ->onWorkflowUpdate(new WorkflowUpdateEvent($state));
    }
}
