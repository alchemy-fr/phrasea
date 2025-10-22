<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\AssetAttachment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AssetAttachmentCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetAttachment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attachment')
            ->setEntityLabelInPlural('Attachments')
            ->setSearchFields(['id', 'priority'])
            ->setPaginatorPageSize(200);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('asset'))
            ->add(EntityFilter::new('file'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield AssociationField::new('asset');
        yield AssociationField::new('file');
        yield IntegerField::new('priority');

    }
}
