<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller\Acl;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\AbstractAdminController;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/acl', name: 'alchemy_admin_acl_')]
class GlobalPermissionsController extends AbstractAdminController
{
    private readonly PermissionView $permissionView;

    public function __construct(PermissionView $permissionView)
    {
        $this->permissionView = $permissionView;
    }

    #[Route(path: '/aces/{type}/global', name: 'global_permissions')]
    public function globalPermissionsAction(string $type)
    {
        return $this->render(
            '@AlchemyAcl/easyadmin3/global/acl.html.twig',
            $this->permissionView->getViewParameters($type, null)
        );
    }
}
