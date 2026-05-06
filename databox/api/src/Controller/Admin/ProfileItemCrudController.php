<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Profile\ProfileItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProfileItemCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProfileItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Profile Item')
            ->setEntityLabelInPlural('Profile Items')
            ->setSearchFields(['id', 'definition', 'profile', 'key'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['profile' => 'ASC', 'position' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('profile'))
            ->add(AssociationIdentifierFilter::new('definition'))
            ->add('key')
            ->add('section')
            ->add('type')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('profile')
            ->autocomplete();
        yield AssociationField::new('definition')
            ->autocomplete();
        yield ChoiceField::new('section')
            ->setChoices(ProfileItem::SECTIONS);
        yield ChoiceField::new('type')
            ->setChoices(ProfileItem::TYPES);
        yield TextField::new('key');
        yield BooleanField::new('displayEmpty');
        yield TextField::new('format');
        yield NumberField::new('position');
    }
}
