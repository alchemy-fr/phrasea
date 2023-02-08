<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Admin\ESIndexState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ESIndexStateCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return ESIndexState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // ->remove(Crud::PAGE_INDEX, Action::NEW)    // todo ea3 : check if adding a indexstate is possible
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('ESIndexState')
            ->setEntityLabelInPlural('ESIndexState')
            ->setSearchFields(['id', 'indexName', 'mapping']);
    }

    public function configureFields(string $pageName): iterable
    {
        $indexName = TextField::new('indexName');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $mapping = JsonField::new('mapping');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $indexName, $createdAt, $updatedAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $indexName, $mapping, $createdAt, $updatedAt];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$indexName, $createdAt, $updatedAt];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$indexName, $createdAt, $updatedAt];
        }

        return [];
    }
}
