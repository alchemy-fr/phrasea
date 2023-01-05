<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;


use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;


abstract class AbstractAdminCrudController extends AbstractCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // ...
            ->add(Crud::PAGE_INDEX, Action::DETAIL)  // todo: EA3 ; disable "show" action ?
//            ->remove(Crud::PAGE_INDEX, Action::EDIT)
//            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ->showEntityActionsInlined()
            ;
    }
}