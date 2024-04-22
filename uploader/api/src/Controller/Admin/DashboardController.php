<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\FailedEvent;
use App\Entity\FormSchema;
use App\Entity\Target;
use App\Entity\TargetParams;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

class DashboardController extends AbstractAdminDashboardController
{
    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Target params permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'target_params']),
            MenuItem::linkToRoute('Form schema permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'form_schema']),
            MenuItem::linkToCrud('All permissions (advanced)', '', AccessControlEntry::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('Target', '', Target::class),
            MenuItem::linkToCrud('Commit', '', Commit::class),
            MenuItem::linkToCrud('Asset', '', Asset::class),
            MenuItem::linkToCrud('MultipartUpload', '', MultipartUpload::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('FormSchema', '', FormSchema::class),
            MenuItem::linkToCrud('TargetParams', '', TargetParams::class),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Uploads', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::subMenu('Data', 'fas fa-folder-open')->setSubItems($submenu3);

        yield $this->createDevMenu();
    }
}
