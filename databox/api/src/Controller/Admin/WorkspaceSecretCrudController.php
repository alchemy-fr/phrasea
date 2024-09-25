<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Integration\WorkspaceSecret;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

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
            ->add(TextFilter::new('name'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('workspace');    
        yield TextField::new('name');
        yield TextField::new('plainValue', 'Value')
            ->setFormType(PasswordType::class)
            ->onlyOnForms();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
