<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TargetCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Target::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Target')
            ->setEntityLabelInPlural('Target')
            ->setSearchFields(['id', 'slug', 'name', 'description', 'targetUrl', 'defaultDestination', 'targetAccessToken', 'targetTokenType', 'allowedGroups'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $slug = TextField::new('slug');
        $name = TextField::new('name');
        $description = TextareaField::new('description');
        $targetUrl = TextField::new('targetUrl');
        $targetTokenType = TextField::new('targetTokenType');
        $targetAccessToken = TextField::new('targetAccessToken');
        $defaultDestination = TextField::new('defaultDestination');
        $allowedGroups = TextField::new('allowedGroups');
        $enabled = Field::new('enabled');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $targetParams = AssociationField::new('targetParams');
        $pullModeUrl = TextareaField::new('pullModeUrl', 'Pull mode URL')->setTemplatePath('@AlchemyAdmin/list/code.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $slug, $name, $pullModeUrl, $targetUrl, $enabled, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $slug, $name, $enabled, $description, $targetUrl, $defaultDestination, $targetAccessToken, $targetTokenType, $allowedGroups, $createdAt, $targetParams];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups, $enabled];
        }
        return [];
    }
}
