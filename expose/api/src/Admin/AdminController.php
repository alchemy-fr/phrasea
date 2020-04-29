<?php

declare(strict_types=1);

namespace App\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    use PermissionTrait;
}
