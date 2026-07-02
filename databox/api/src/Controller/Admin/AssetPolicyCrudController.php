<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AssetPolicyCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AssetPolicy::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset Policy')
            ->setEntityLabelInPlural('Asset Policies')
            ->setSearchFields(['id', 'name', 'key'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['workspace.name' => 'ASC', 'name' => 'ASC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('enabled'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add($this->userChoiceFilter->createFilter('ownerId'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('workspace')
            ->autocomplete();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield IntegerField::new('priority');
        yield TextField::new('name');
        yield ArrayField::new('userIds')
            ->onlyOnDetail();
        yield ArrayField::new('groupIds')
            ->onlyOnDetail();
        yield BooleanField::new('enabled');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield JsonField::new('conditions')->hideOnIndex();
        yield JsonField::new('actions')->hideOnIndex();
    }
}
