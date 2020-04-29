<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

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
        return $this->render('@AlchemyAcl/permissions/acl.html.twig',
            $this->permissionView->getAclViewParameters(
                $this->entity['class'],
                $this->request->query->get('id')
            ));
    }
}
