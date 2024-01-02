<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\GroupChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
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
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'slug', 'name', 'description', 'targetUrl', 'defaultDestination', 'targetAccessToken', 'targetTokenType', 'allowedGroups']);
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $slug = TextField::new('slug');
        $description = TextareaField::new('description');
        $targetUrl = TextField::new('targetUrl')
            ->setHelp('Leave empty for pull mode. i.e: "https://phraseanet.phrasea.local/api/v1/upload/enqueue/" for Phraseanet, "http://databox-api/incoming-uploads" for Databox upload');
        $targetTokenType = TextField::new('targetTokenType')
            ->setHelp('Use "OAuth" for Phraseanet')
            ->setFormTypeOptions(['attr' => ['placeholder' => 'Defaults to "Bearer"']]);
        $targetAccessToken = TextField::new('targetAccessToken');
        $defaultDestination = TextField::new('defaultDestination')
            ->setHelp('i.e: "42" (for Phraseanet collection), "cdc3679f-3f37-4260-8de7-b649ecc8c1cc" (for Databox collection)');
        $allowedGroups = GroupChoiceField::new('allowedGroups');
        $enabled = Field::new('enabled');
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');
        $pullModeUrl = TextareaField::new('pullModeUrl', 'Pull mode URL')
            ->setTemplatePath('@AlchemyAdmin/list/code.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $slug, $name, $pullModeUrl, $targetUrl, $enabled, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$slug, $name, $description, $targetUrl, $targetTokenType, $targetAccessToken, $defaultDestination, $allowedGroups, $enabled];
        }

        return [];
    }
}
