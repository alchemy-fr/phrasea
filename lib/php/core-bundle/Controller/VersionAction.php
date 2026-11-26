<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The version of the image (PHRASEA_VERSION, set by the CI at build time: the
 * tag name for a release) and its commit. Public: read by the dashboard.
 */
#[AsController]
final readonly class VersionAction
{
    public function __construct(
        #[Autowire(param: 'alchemy_core.version')]
        private string $version,
        #[Autowire(param: 'alchemy_core.commit')]
        private string $commit,
    ) {
    }

    #[Route(path: '/_health/version', name: 'alchemy_core_version', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse([
            'version' => $this->version,
            'commit' => '' !== $this->commit ? $this->commit : null,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
