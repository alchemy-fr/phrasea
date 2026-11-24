<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Core\AssetAttachment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AssetAttachmentCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetAttachment::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attachment')
            ->setEntityLabelInPlural('Attachments')
            ->setSearchFields(['id', 'priority'])
            ->setPaginatorPageSize(200);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add(AssociationIdentifierFilter::new('attachment'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield AssociationField::new('asset')
            ->autocomplete();
        yield AssociationField::new('attachment')
            ->autocomplete();
        yield IntegerField::new('priority');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
