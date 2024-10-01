<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Template\TemplateAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
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
            ->add(EntityFilter::new('template'))
            ->add(EntityFilter::new('definition'))
            ->add(TextFilter::new('value'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('template');
        yield AssociationField::new('definition');
        yield TextField::new('value');
    }
}
