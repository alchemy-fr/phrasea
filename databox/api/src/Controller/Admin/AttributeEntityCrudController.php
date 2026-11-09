<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Core\AttributeEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AttributeEntityCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AttributeEntity::class;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(EntityFilter::new('list'))
            ->add(TextFilter::new('value'))
            ->add(EntityFilter::new('parent'))
            ->add(NumericFilter::new('hierarchyDepth'))
            ->add(BooleanFilter::new('placeholder'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add($this->userChoiceFilter->createFilter('creatorId'))
            ->add(ChoiceFilter::new('status')
                ->setChoices(AttributeEntity::STATUS_CHOICES)
            )
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute Entity')
            ->setEntityLabelInPlural('Attribute Entities')
            ->setSearchFields(['id', 'position', 'value']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace')
            ->autocomplete();
        yield $this->userChoiceField->create('creatorId', 'Creator');
        yield AssociationField::new('list')
            ->autocomplete();
        yield TextField::new('value');
        // The hierarchy is edited through the API only, which shifts the depth
        // of the moved subtree and indexes it again (EntityHierarchyManager)
        yield AssociationField::new('parent')
            ->hideOnForm();
        yield IntegerField::new('hierarchyDepth')
            ->hideOnForm();
        yield BooleanField::new('placeholder')
            ->renderAsSwitch(false)
            ->hideOnForm();
        yield IntegerField::new('position')
            ->hideOnForm();
        yield TextField::new('note');
        yield TextField::new('emoji');
        yield JsonField::new('synonyms')
            ->hideOnIndex();
        yield ChoiceField::new('status')
        ->setChoices(AttributeEntity::STATUS_CHOICES);
        yield JsonField::new('translations');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
