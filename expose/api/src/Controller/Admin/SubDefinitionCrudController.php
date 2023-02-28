<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\SubDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SubDefinitionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return SubDefinition::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('SubDefinition')
            ->setEntityLabelInPlural('SubDefinition');
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $path = TextField::new('path');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $mimeType = TextField::new('mimeType');
        $createdAt = DateTimeField::new('createdAt');
        $asset = AssociationField::new('asset');
        $id = IdField::new();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $asset, $size, $path, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $path, $size, $mimeType, $createdAt, $asset];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $path, $size, $mimeType, $createdAt, $asset];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $path, $size, $mimeType, $createdAt, $asset];
        }

        return [];
    }
}
