<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Automation\SavedSearchAutomation;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The runs of an automation, newest first, to whoever may read the automation.
 */
final class AutomationRunCollectionProvider implements ProviderInterface
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;
    use ParameterValuesTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $automationId = self::getParameterValue($operation, 'automation');
        $automation = is_string($automationId) && Uuid::isValid($automationId)
            ? $this->em->find(SavedSearchAutomation::class, $automationId)
            : null;
        if (!$automation instanceof SavedSearchAutomation) {
            throw new NotFoundHttpException('Automation not found');
        }

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $automation);

        // The "automation" parameter restricts the collection to the automation
        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
