<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

use Alchemy\AclBundle\Controller\PermissionController;

trait PermissionTrait
{
    public function permissionsAction()
    {
        return $this->forward(PermissionController::class.'::acl', [
            'entityClass' => $this->entity['class'],
            'id' => $this->request->query->get('id'),
        ], $this->request->query->all());
    }
}
