<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Entity\Template\AssetDataTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AssetDataTemplateCrudController extends AbstractAclAdminCrudController
{
    private UserChoiceField $userChoiceField;

    public static function getEntityFqcn(): string
    {
        return AssetDataTemplate::class;
    }

    public function __construct(UserChoiceField $userChoiceField)
    {
        $this->userChoiceField = $userChoiceField;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Data Template')
            ->setEntityLabelInPlural('Data Templates')
            ->setSearchFields(['id', 'name', 'ownerId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('ownerId'));
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();
        $title = TextField::new('name');
        $workspace = AssociationField::new('workspace');
        $tags = AssociationField::new('tags');
        $privacy = PrivacyField::new('privacy');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $attributes = AssociationField::new('attributes');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $workspace, $privacy, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerUser, $createdAt, $updatedAt, $privacy, $tags, $attributes, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        }

        return [];
    }
}
