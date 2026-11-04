<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\SavedSearch\SavedSearch;
use App\Repository\Automation\SavedSearchAutomationRepository;
use App\Security\Voter\SavedSearchAutomationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The automations of a saved search, listed to its owner only.
 */
final class SavedSearchAutomationCollectionProvider implements ProviderInterface
{
    use SecurityAwareTrait;
    use ParameterValuesTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SavedSearchAutomationRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $savedSearchId = self::getParameterValue($operation, 'savedSearch');
        $savedSearch = is_string($savedSearchId) && Uuid::isValid($savedSearchId)
            ? $this->em->find(SavedSearch::class, $savedSearchId)
            : null;
        if (!$savedSearch instanceof SavedSearch) {
            throw new NotFoundHttpException('Saved search not found');
        }

        $this->denyAccessUnlessGranted(SavedSearchAutomationVoter::MANAGE_AUTOMATIONS, $savedSearch);

        return $this->repository->findBySavedSearch($savedSearch->getId());
    }
}
