<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Doctrine\Delete\WorkspaceDelete;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteWorkspaceHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'delete_workspace';

    private WorkspaceDelete $workspaceDelete;

    public function __construct(WorkspaceDelete $workspaceDelete)
    {
        $this->workspaceDelete = $workspaceDelete;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->workspaceDelete->deleteWorkspace($payload['id']);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $id): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $id,
        ]);
    }
}
