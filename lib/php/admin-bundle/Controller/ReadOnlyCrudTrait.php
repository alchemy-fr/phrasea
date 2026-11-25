<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a CRUD controller read-only: its entities can be listed and viewed only.
 *
 * Actions::disable() neither hides nor forbids anything for an admin, since the AdminVoter
 * grants every attribute to the admins: the write actions are removed from the pages by
 * removeWriteActions(), and refused here whatever URL is called.
 */
trait ReadOnlyCrudTrait
{
    /**
     * Removes the write actions from the list and detail pages, to call from configureActions().
     */
    protected function removeWriteActions(Actions $actions): Actions
    {
        foreach ([
            Crud::PAGE_INDEX => [Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE],
            Crud::PAGE_DETAIL => [Action::EDIT, Action::DELETE],
        ] as $pageName => $actionNames) {
            foreach ($actionNames as $actionName) {
                try {
                    $actions->remove($pageName, $actionName);
                } catch (\InvalidArgumentException) {
                    // Already removed
                }
            }
        }

        return $actions;
    }

    #[\Override]
    public function new(AdminContext $context): KeyValueStore|Response
    {
        throw new ForbiddenActionException($context);
    }

    #[\Override]
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        throw new ForbiddenActionException($context);
    }

    #[\Override]
    public function delete(AdminContext $context): KeyValueStore|Response
    {
        throw new ForbiddenActionException($context);
    }

    #[\Override]
    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        throw new ForbiddenActionException($context);
    }
}
