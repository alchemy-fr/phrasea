<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Automation;

use App\Automation\AutomationCursor;
use App\Automation\AutomationRunner;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Entity\Automation\SavedSearchAutomation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ResetAutomationCursorHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AutomationCursor $cursor,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(ResetAutomationCursor $message): void
    {
        $automation = $this->em->find(SavedSearchAutomation::class, $message->automationId);
        if (!$automation instanceof SavedSearchAutomation) {
            return;
        }

        // Not while a run moves the cursor
        $lock = $this->lockFactory->createLock(AutomationRunner::getLockKey($automation->getId()));
        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException(sprintf('Automation "%s" is running', $automation->getId()));
        }

        try {
            $this->cursor->markCurrentResultsAsSeen($automation);
        } catch (AutomationOwnerUnavailableException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } finally {
            $lock->release();
        }
    }
}
