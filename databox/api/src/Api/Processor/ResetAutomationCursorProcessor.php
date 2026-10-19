<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Automation\ResetCursorInput;
use App\Automation\AutomationCursor;
use App\Consumer\Handler\Automation\ResetAutomationCursor;
use App\Entity\Automation\SavedSearchAutomation;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * "empty" forgets the handled assets at once; "current" marks the current results
 * as handled in the worker (the search is run as the owner).
 */
final class ResetAutomationCursorProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AutomationCursor $cursor,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param ResetCursorInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $automation = DoctrineUtil::findStrict($this->em, SavedSearchAutomation::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $automation);

        switch ($data->mode) {
            case ResetCursorInput::MODE_EMPTY:
                $this->cursor->empty($automation);
                break;
            case ResetCursorInput::MODE_CURRENT:
                $this->bus->dispatch(new ResetAutomationCursor($automation->getId()));
                break;
            default:
                $message = sprintf('The mode must be one of: %s, %s.', ResetCursorInput::MODE_EMPTY, ResetCursorInput::MODE_CURRENT);

                throw new ValidationException(new ConstraintViolationList([new ConstraintViolation($message, $message, [], null, 'mode', $data->mode)]));
        }

        return new Response('', 204);
    }
}
