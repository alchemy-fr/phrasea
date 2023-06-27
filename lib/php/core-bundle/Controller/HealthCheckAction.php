<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Controller;

use Alchemy\CoreBundle\Health\HealthChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckAction extends AbstractController
{
    public function __construct(private readonly HealthChecker $healthChecker)
    {
    }

    public function __invoke(Request $request): Response
    {
        $checks = $this->healthChecker->getChecks();

        $errored = array_filter($checks, fn(array $check): bool => !$check['ok']);

        return new JsonResponse($checks, empty($errored) ? 200 : 503);
    }
}
