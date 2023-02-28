<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class AssetCrudController extends AbstractAclAdminCrudController
{
    private UserChoiceField $userChoiceField;

    public static function getEntityFqcn(): string
    {
        return Asset::class;
    }

    public function __construct(UserChoiceField $userChoiceField)
    {
        $this->userChoiceField = $userChoiceField;;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy'])
            ->setPaginatorPageSize(200);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'));
    }

    public function configureFields(string $pageName): iterable
    {
        $privacyChoices = [];
        foreach (WorkspaceItemPrivacyInterface::LABELS as $value=>$label) {
            $privacyChoices[$label] = $value;
        }

        /** @var Asset $asset */
        $asset = $this->getContext()->getEntity()->getInstance();

        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $tags = AssociationField::new('tags');
        $privacy = ChoiceField::new('privacy')->setChoices($privacyChoices);
        $ownerId = TextField::new('ownerId');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $id = \Alchemy\AdminBundle\Field\IdField::new();
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $locale = TextField::new('locale');
        $collections = AssociationField::new('collections');
        $storyCollection = AssociationField::new('storyCollection');
        $referenceCollection = AssociationField::new('referenceCollection');
        $attributes = AssociationField::new('attributes');
        $file = Field::new('file');
        $source = AssociationField::new('source');
        $renditions = AssociationField::new('renditions');
        $collectionsCount = IntegerField::new('collections.count', '# Colls');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $workspace, $privacy, $collectionsCount, $source, $key, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerId, $key, $createdAt, $updatedAt, $locale, $privacy, $collections, $tags, $storyCollection, $referenceCollection, $attributes, $file, $renditions, $workspace];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        }

        return [];
    }
}
