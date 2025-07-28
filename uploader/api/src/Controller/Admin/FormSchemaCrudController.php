<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\FormSchema;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class FormSchemaCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return FormSchema::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('FormSchema')
            ->setEntityLabelInPlural('FormSchema')
            ->setSearchFields(['id', 'locale', 'data']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('target'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('target');
        yield TextField::new('locale');
        yield ChoiceField::new('localeMode')
            ->setChoices([
                'No locale' => FormSchema::LOCALE_MODE_NO_LOCALE,
                'Use user agent' => FormSchema::LOCALE_MODE_USE_UA,
                'Force with this locale' => FormSchema::LOCALE_MODE_FORCED,
            ])
        ;
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
        yield JsonField::new('data', 'jsonData');
    }
}
