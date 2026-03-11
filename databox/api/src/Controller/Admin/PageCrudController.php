<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Page\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PageCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Page')
            ->setEntityLabelInPlural('Pages')
            ->setSearchFields(['id', 'title'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['title' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(TextFilter::new('slug'))
            ->add(BooleanFilter::new('public'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'))
            ->add($this->userChoiceFilter->createFilter('ownerId'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield TextField::new('title');
        yield TextField::new('slug');
        yield BooleanField::new('public')
            ->renderAsSwitch(false)
        ;
        yield BooleanField::new('enabled')
            ->renderAsSwitch()
        ;
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield JsonField::new('data')
        ->hideOnIndex();
        yield JsonField::new('options')
        ->hideOnIndex();
    }
}
