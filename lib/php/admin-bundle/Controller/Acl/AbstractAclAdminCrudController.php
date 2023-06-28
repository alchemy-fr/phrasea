<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller\Acl;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAclAdminCrudController extends AbstractAdminCrudController
{
    protected PermissionView $permissionView;
    protected ObjectMapping $objectMapping;

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'alchemy_admin_acl_global_permissions',
                [
                    'type' => $this->objectMapping->getObjectKey(static::getEntityFqcn()),
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

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $entity = $adminContext->getEntity()->getInstance();
        $id = $entity->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->objectMapping->getObjectKey($entity::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/easyadmin3/entity/acl.html.twig', $twigParameters);
    }

    #[Required]
    public function setObjectMapping(ObjectMapping $objectMapping): void
    {
        $this->objectMapping = $objectMapping;
    }

    #[Required]
    public function setPermissionView(PermissionView $permissionView): void
    {
        $this->permissionView = $permissionView;
    }
}
