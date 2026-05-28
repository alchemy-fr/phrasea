<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\CollectionAccess;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class CollectionAccessCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly PrivacyField $privacyField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionAccess::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Collection Access')
            ->setEntityLabelInPlural('Collection Access')
            ->setSearchFields(['id', 'collection', 'userId', 'privacy'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('collection'))
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('userId')
                ->setFormTypeOption('comparison_type_options', [
                    'type' => 'entity',
                ]))
            ->add(TextFilter::new('privacy'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace');
        yield AssociationField::new('collection');
        yield TextField::new('userId');
        yield TextField::new('path');
        yield $this->privacyField->create('privacy');

    }
}
