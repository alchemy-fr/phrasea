<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\GroupChoiceField;
use Alchemy\AdminBundle\Form\GroupChoiceType;
use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
        $targetUrl = TextField::new('targetUrl')
            ->setHelp('i.e: "https://phraseanet.phrasea.local/api/v1/upload/enqueue/" for Phraseanet, "http://databox-api/incoming-uploads" for Databox upload');
        $targetTokenType = TextField::new('targetTokenType');
        $targetAccessToken = TextField::new('targetAccessToken');
        $defaultDestination = TextField::new('defaultDestination')
            ->setHelp('i.e: "42" (for Phraseanet collection), "cdc3679f-3f37-4260-8de7-b649ecc8c1cc" (for Databox collection)');
        $allowedGroups = GroupChoiceField::new('allowedGroups');
        $enabled = Field::new('enabled');
        $id = IdField::new('id', 'ID')
            ->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $targetParams = AssociationField::new('targetParams');
        $pullModeUrl = TextareaField::new('pullModeUrl', 'Pull mode URL')
            ->setTemplatePath('@AlchemyAdmin/list/code.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $enabled, $slug, $name, $pullModeUrl, $targetUrl, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            // todo: EA3 display allowedGroups on detail page ? (now "array to string conversion" error if added)
            return [$id, $enabled, $slug, $name, $description, $targetUrl, $defaultDestination, $targetAccessToken, $targetTokenType, $createdAt, $targetParams];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$enabled, $slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$enabled, $slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups];
        }
        return [];
    }
}
