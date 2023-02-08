<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Core\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

class CollectionCrudController extends AbstractAdminCrudController
{
    use PermissionTrait;

    private UserChoiceField $userChoiceField;

    public static function getEntityFqcn(): string
    {
        return Collection::class;
    }

    public function __construct(UserChoiceField $userChoiceField)
    {
        $this->userChoiceField = $userChoiceField;
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'collection',
                ]
            )
            ->createAsGlobalAction();

        $permissionsAction = Action::new('permissions')
            ->linkToCrudAction('permissions')
        ;

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $globalPermissionsAction)
            ->add(Crud::PAGE_INDEX, $permissionsAction);
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
        $privacyChoices = [];
        foreach (WorkspaceItemPrivacyInterface::LABELS as $value=>$label) {
            $privacyChoices[$label] = $value;
        }

        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $parent = AssociationField::new('parent');
        $privacyTxt = IntegerField::new('privacy')->setTemplatePath('admin/field_privacy.html.twig');
        $privacy = ChoiceField::new('privacy')->setChoices($privacyChoices);
        $ownerId = TextField::new('ownerId');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $deletedAt = DateTimeField::new('deletedAt');
        $locale = TextField::new('locale');
        $children = AssociationField::new('children');
        $assets = AssociationField::new('assets');
        $referenceAssets = AssociationField::new('referenceAssets');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $parent, $workspace, $privacyTxt, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerId, $key, $createdAt, $updatedAt, $deletedAt, $locale, $privacyTxt, $parent, $children, $assets, $referenceAssets, $workspace];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $parent, $privacy, $ownerUser];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $parent, $privacy, $ownerUser];
        }

        return [];
    }

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Collection $collection */
        $collection = $adminContext->getEntity()->getInstance();
        $id = $collection->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->permissionView->getObjectKey(Collection::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig', $twigParameters);
    }
}
