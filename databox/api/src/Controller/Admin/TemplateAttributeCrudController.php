<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Template\TemplateAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TemplateAttributeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TemplateAttribute::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            AssociationField::new('template'),
            AssociationField::new('definition'),
            TextField::new('value'),
        ];
    }
}
