<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\AdminConfigRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractAdminController extends AbstractController
{
    protected AdminConfigRegistry $adminConfigRegistry;

    /**
     * @required
     */
    public function setAdminConfigRegistry(AdminConfigRegistry $adminConfigRegistry): void
    {
        $this->adminConfigRegistry = $adminConfigRegistry;
    }
}
