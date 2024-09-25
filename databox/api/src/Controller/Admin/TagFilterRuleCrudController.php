<?php

namespace App\Controller\Admin;

use App\Entity\Core\TagFilterRule;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class TagFilterRuleCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return TagFilterRule::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TagFilterRule')
            ->setEntityLabelInPlural('TagFilterRule')
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('objectType')->setChoices([
                'collection' => TagFilterRule::TYPE_COLLECTION ,
                'workspace' => TagFilterRule::TYPE_WORKSPACE
                ]))
            ->add(ChoiceFilter::new('userType')->setChoices([
                'user'  => TagFilterRule::TYPE_USER,
                'group' => TagFilterRule::TYPE_GROUP
            ]))    
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield IntegerField::new('userType');
        yield TextField::new('userId');
        yield IntegerField::new('objectType');
        yield TextField::new('objectId');
        yield AssociationField::new('include');
        yield AssociationField::new('exclude');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('updatedAt')
            ->hideOnIndex();
    }
}
