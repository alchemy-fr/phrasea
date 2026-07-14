<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'alchemy_admin_')]
class PhpInfoController extends AbstractAdminController
{
    #[Route(path: '/php-info', name: 'phpinfo')]
    public function phpInfoAction()
    {
        $this->denyAccessUnlessGranted(JwtUser::ROLE_TECH);
        ob_start();
        phpinfo();
        $content = ob_get_contents();
        ob_end_clean();

        return new Response($content);
    }
}
