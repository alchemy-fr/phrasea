<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

class AdminAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return new Response($this->twig->render('@AlchemyAdmin/403_logout.html.twig'), 403);
    }
}
