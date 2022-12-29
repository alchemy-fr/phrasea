<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\TargetParams;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TargetParamsCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return TargetParams::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TargetParams')
            ->setEntityLabelInPlural('TargetParams')
            ->setSearchFields(['id', 'data'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $jsonData = Field::new('jsonData');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $data = TextField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $data, $createdAt, $updatedAt, $target];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $jsonData];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $jsonData];
        }
        return [];
    }
}
