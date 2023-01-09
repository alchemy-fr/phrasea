<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\TargetParams;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class TargetParamsCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return TargetParams::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $permissionsAction = Action::new('permissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'target_params',
                ]
            )
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $permissionsAction);

    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TargetParams')
            ->setEntityLabelInPlural('TargetParams')
            ->setSearchFields(['id', 'data']);
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $jsonData = TextAreaField::new('jsonData');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $data = JsonField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $createdAt, $updatedAt, $target, $data];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $jsonData];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $jsonData];
        }

        return [];
    }
}
