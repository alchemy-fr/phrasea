<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class CollectionCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly PrivacyField $privacyField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Collection::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Collection')
            ->setEntityLabelInPlural('Collection')
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'));
    }

    public function configureFields(string $pageName): iterable
    {
        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $parent = AssociationField::new('parent');
        $privacy = $this->privacyField->create('privacy');
        $ownerId = TextField::new('ownerId');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $id = IdField::new();
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $deletedAt = DateTimeField::new('deletedAt');
        $locale = TextField::new('locale');
        $children = AssociationField::new('children');
        $assets = AssociationField::new('assets');
        $referenceAssets = AssociationField::new('referenceAssets');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $parent, $workspace, $privacy, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerId, $key, $createdAt, $updatedAt, $deletedAt, $locale, $privacy, $parent, $children, $assets, $referenceAssets, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $parent, $privacy, $ownerUser];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $parent, $privacy, $ownerUser];
        }

        return [];
    }
}
