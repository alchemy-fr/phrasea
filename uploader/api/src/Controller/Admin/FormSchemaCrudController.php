<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\Commit;
use App\Entity\FormSchema;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class FormSchemaCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return FormSchema::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $permissionsAction = Action::new('permissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'form_schema',
                ]
            );

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $permissionsAction)
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('FormSchema')
            ->setEntityLabelInPlural('FormSchema')
            ->setSearchFields(['id', 'locale', 'data'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $locale = TextField::new('locale');
        $jsonData = TextAreaField::new('jsonData');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $data = JsonField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $locale, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $locale, $createdAt, $updatedAt, $target, $data];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $locale, $jsonData];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $locale, $jsonData];
        }
        return [];
    }
}
