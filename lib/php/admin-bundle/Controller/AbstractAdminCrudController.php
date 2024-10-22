<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

abstract class AbstractAdminCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(50)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ->showEntityActionsInlined()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    protected function returnToReferer(AdminContext $context): RedirectResponse
    {
        return $this->redirect($context->getReferrer()
            ?? $this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
    }
}
