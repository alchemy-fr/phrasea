<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Core\AttributeClass;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class AttributeClassCrudController extends AbstractAdminCrudController
{
    use PermissionTrait;

    public static function getEntityFqcn(): string
    {
        return AttributeClass::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'attributeClass',
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
            ->setEntityLabelInSingular('AttributeClass')
            ->setEntityLabelInPlural('AttributeClass')
            ->setSearchFields(['id', 'name', 'key'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('name')
            ->add('public')
            ->add('editable');
    }

    public function configureFields(string $pageName): iterable
    {
        $workspace = AssociationField::new('workspace');
        $name = TextField::new('name');
        $public = Field::new('public');
        $editable = Field::new('editable');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $definitions = AssociationField::new('definitions');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $name, $public, $editable, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $editable, $public, $key, $createdAt, $workspace, $definitions];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $name, $public, $editable];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $name, $public, $editable];
        }

        return [];
    }

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var AttributeClass $attributeClass */
        $attributeClass = $adminContext->getEntity()->getInstance();
        $id = $attributeClass->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->permissionView->getObjectKey(AttributeClass::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig', $twigParameters);
    }
}
