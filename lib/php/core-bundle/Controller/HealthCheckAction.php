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
    private HealthChecker $healthChecker;

    public function __construct(HealthChecker $healthChecker)
    {
        $this->healthChecker = $healthChecker;
    }

    public function __invoke(Request $request): Response
    {
        $checks = $this->healthChecker->getChecks();

        $errored = array_filter($checks, function (array $check): bool {
            return !$check['ok'];
        });

        return new JsonResponse($checks, empty($errored) ? 200 : 503);
    }
}
