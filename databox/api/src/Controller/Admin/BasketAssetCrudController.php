<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Basket\BasketAsset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class BasketAssetCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BasketAsset::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('basket'))
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('basket')
            ->autocomplete();
        yield AssociationField::new('asset')
            ->autocomplete();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield JsonField::new('context')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
