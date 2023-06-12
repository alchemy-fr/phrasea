<?php

namespace App\Controller\Admin;

use App\Entity\Core\MetadataTag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetadataTagController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MetadataTag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('MetadataTag')
            ->setEntityLabelInPlural('MetadataTags')
            ->setSearchFields(['id', 'label', 'className'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('id');
        yield TextField::new('label');
        yield TextField::new('className');
    }
}
