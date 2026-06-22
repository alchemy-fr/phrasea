<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Template\TemplateAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class TemplateAttributeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TemplateAttribute::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('template'))
            ->add(EntityFilter::new('definition'))
            ->add(TextFilter::new('value'))
            ->add(BooleanFilter::new('invalid'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('template')
            ->autocomplete();
        yield AssociationField::new('definition')
            ->autocomplete();
        yield TextField::new('value');
        yield IntegerField::new('position');
        yield BooleanField::new('invalid');
    }
}
