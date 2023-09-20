<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Integration\WorkspaceSecret;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class WorkspaceSecretCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkspaceSecret::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'name'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('name')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $workspace = AssociationField::new('workspace');
        $name = TextField::new('name');
        $plainValue = TextField::new('plainValue', 'Value')
            ->setFormType(PasswordType::class);
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $name, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $createdAt, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $name, $plainValue];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $name, $plainValue];
        }

        return [];
    }
}
