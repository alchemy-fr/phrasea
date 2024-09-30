<?php

namespace App\Controller\Admin;

use App\Entity\Basket\Basket;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class BasketCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Basket::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'))
            ->add($this->userChoiceFilter->createFilter('ownerId'))
        ;
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield TextField::new('title');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
