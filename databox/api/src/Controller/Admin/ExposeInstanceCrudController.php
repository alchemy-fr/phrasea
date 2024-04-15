<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\File;
use App\Entity\Expose\ExposeInstance;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ExposeInstanceCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExposeInstance::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield UrlField::new('url', 'Expose API URL')
            ->setHelp('i.e. https://api-expose.acme.phrasea.io');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
