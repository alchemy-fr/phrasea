<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\Doctrine\Entity\JobState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JobStateCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return JobState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Job State')
            ->setEntityLabelInPlural('Job States')
            ->setSearchFields(['id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $state = TextField::new('state');
        $workflow = AssociationField::new('workflow');
        $id = IdField::new();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workflow, $state];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $workflow, $state];
        }

        return [];
    }
}
