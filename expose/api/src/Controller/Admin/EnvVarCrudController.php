<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\EnvVar;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class EnvVarCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return EnvVar::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Env Var')
            ->setEntityLabelInPlural('Env Vars')
            ->setSearchFields(['name']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(DateTimeFilter::new('updatedAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        // May hold a secret: never listed
        $value = TextareaField::new('value');
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        return match ($pageName) {
            Crud::PAGE_INDEX => [$name, $createdAt, $updatedAt],
            Crud::PAGE_DETAIL => [$id, $name, $value, $createdAt, $updatedAt],
            default => [$name, $value],
        };
    }
}
