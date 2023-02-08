<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceCrudController extends AbstractAdminCrudController
{
    use PermissionTrait;

    private UserChoiceField $userChoiceField;

    public static function getEntityFqcn(): string
    {
        return Workspace::class;
    }

    public function __construct(PermissionView $permissionView, UserChoiceField $userChoiceField)
    {
        $this->setPermissionView($permissionView);
        $this->userChoiceField = $userChoiceField;
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'workspace',
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
            ->setEntityLabelInSingular('Workspace')
            ->setEntityLabelInPlural('Workspace')
            ->setSearchFields(['id', 'name', 'slug', 'ownerId', 'config', 'enabledLocales', 'localeFallbacks'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $slug = TextField::new('slug');
        $ownerId = TextField::new('ownerId');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $enabledLocales = ArrayField::new('enabledLocales');
        $localeFallbacks = ArrayField::new('localeFallbacks');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $config = TextField::new('config');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $deletedAt = DateTimeField::new('deletedAt');
        $collections = AssociationField::new('collections');
        $tags = AssociationField::new('tags');
        $renditionClasses = AssociationField::new('renditionClasses');
        $renditionDefinitions = AssociationField::new('renditionDefinitions');
        $attributeDefinitions = AssociationField::new('attributeDefinitions');
        $files = AssociationField::new('files');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $slug, $enabledLocales, $localeFallbacks, $updatedAt, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $ownerId, $config, $enabledLocales, $localeFallbacks, $createdAt, $updatedAt, $deletedAt, $collections, $tags, $renditionClasses, $renditionDefinitions, $attributeDefinitions, $files];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $slug, $ownerUser, $enabledLocales, $localeFallbacks];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $slug, $ownerUser, $enabledLocales, $localeFallbacks];
        }

        return [];
    }

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Workspace $workspace */
        $workspace = $adminContext->getEntity()->getInstance();
        $id = $workspace->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->permissionView->getObjectKey(Workspace::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig', $twigParameters);
    }
}
