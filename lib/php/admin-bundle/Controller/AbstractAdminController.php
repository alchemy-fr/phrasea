<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\AdminConfigRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAdminController extends AbstractController
{
    protected AdminConfigRegistry $adminConfigRegistry;

    #[Required]
    public function setAdminConfigRegistry(AdminConfigRegistry $adminConfigRegistry): void
    {
        $this->adminConfigRegistry = $adminConfigRegistry;
    }
}
