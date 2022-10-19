<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

use Symfony\Component\Routing\Annotation\Route;

trait PermissionTrait
{
    protected PermissionView $permissionView;

    /**
     * @required
     */
    public function setPermissionView(PermissionView $permissionView): void
    {
        $this->permissionView = $permissionView;
    }

    public function permissionsAction()
    {
        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig',
            $this->permissionView->getViewParameters(
                $this->permissionView->getObjectKey($this->entity['class']),
                $this->request->query->get('id')
            ));
    }

    /**
     * @Route(path="/aces/{type}/global", name="admin_global_permissions")
     */
    public function globalPermissionsAction(string $type)
    {
        return $this->render(
            '@AlchemyAcl/permissions/global/acl.html.twig',
            $this->permissionView->getViewParameters($type, null)
        );
    }
}
