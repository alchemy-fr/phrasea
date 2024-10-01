<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\Acl\UserTypeChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use App\Admin\Field\RenditionRuleObjectTypeChoiceField;
use App\Entity\Core\RenditionRule;
use App\Entity\Core\TagFilterRule;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class RenditionRuleCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionRule::class;
    }

    public function __construct(private readonly UserTypeChoiceField $userTypeChoiceField)
    {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('RenditionRule')
            ->setEntityLabelInPlural('RenditionRule')
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
        ->add(ChoiceFilter::new('objectType')->setChoices([
            'collection' => TagFilterRule::TYPE_COLLECTION,
            'workspace' => TagFilterRule::TYPE_WORKSPACE,
        ]))
        ->add(ChoiceFilter::new('userType')->setChoices([
            'user' => TagFilterRule::TYPE_USER,
            'group' => TagFilterRule::TYPE_GROUP,
        ]))
        ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield $this->userTypeChoiceField->create('userType');
        yield TextField::new('userId');
        yield RenditionRuleObjectTypeChoiceField::new('objectType');
        yield TextField::new('objectId');
        yield AssociationField::new('allowed');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();

    }
}
