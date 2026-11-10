<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller\Acl;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
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
            ->linkToCrudAction('globalPermissions')
            ->createAsGlobalAction();

        $permissionsAction = Action::new('permissions')
            ->linkToCrudAction('permissions')
        ;

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $globalPermissionsAction)
            ->add(Crud::PAGE_INDEX, $permissionsAction);
    }

    #[AdminRoute('/permissions', name: 'permissions')]
    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var object $entity */
        $entity = $adminContext->getEntity()->getInstance();

        return $this->render('@AlchemyAcl/easyadmin/permissions.html.twig', [
            'back_url' => $this->generateCrudUrl($adminUrlGenerator, Action::INDEX),
            'global_permissions_url' => $this->generateCrudUrl($adminUrlGenerator, 'globalPermissions'),
        ] + $this->permissionView->getViewParameters($this->getObjectKey(), (string) $entity->getId()));
    }

    /**
     * Manages the permissions applying to every object of the entity type.
     */
    #[AdminRoute('/permissions/all', name: 'global_permissions')]
    public function globalPermissions(AdminUrlGenerator $adminUrlGenerator): Response
    {
        return $this->render('@AlchemyAcl/easyadmin/permissions.html.twig', [
            'back_url' => $this->generateCrudUrl($adminUrlGenerator, Action::INDEX),
        ] + $this->permissionView->getViewParameters($this->getObjectKey(), null));
    }

    private function getObjectKey(): string
    {
        return $this->objectMapping->getObjectKey(static::getEntityFqcn());
    }

    private function generateCrudUrl(AdminUrlGenerator $adminUrlGenerator, string $action): string
    {
        return $adminUrlGenerator
            ->unsetAll()
            ->setController(static::class)
            ->setAction($action)
            ->generateUrl();
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
