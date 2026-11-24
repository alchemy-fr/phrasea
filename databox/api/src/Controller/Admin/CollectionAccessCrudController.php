<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\CollectionAccess;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class CollectionAccessCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly PrivacyField $privacyField,
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionAccess::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Collection Access')
            ->setEntityLabelInPlural('Collection Access')
            ->setSearchFields(['id', 'collection', 'userId', 'privacy'])
            ->setPaginatorPageSize(100);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('collection'))
            ->add(EntityFilter::new('workspace'))
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add($this->privacyField->createFilter());
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace');
        yield AssociationField::new('collection');
        yield $this->userChoiceField->create('userId', 'User');
        yield TextField::new('path')
            ->hideOnIndex();
        yield $this->privacyField->create('privacy');

    }
}
