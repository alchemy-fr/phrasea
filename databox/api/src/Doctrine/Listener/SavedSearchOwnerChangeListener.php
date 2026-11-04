<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\SavedSearch\SavedSearch;
use App\Repository\Automation\SavedSearchAutomationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * The automations of a saved search act as its owner: a new owner must enable them again,
 * so that nobody inherits automations acting with their rights without knowing.
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: SavedSearch::class)]
final class SavedSearchOwnerChangeListener
{
    public function preUpdate(SavedSearch $savedSearch, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('ownerId')) {
            return;
        }

        /** @var SavedSearchAutomationRepository $repository */
        $repository = $args->getObjectManager()->getRepository(SavedSearchAutomation::class);
        $repository->disableBySavedSearch($savedSearch->getId());
    }
}
