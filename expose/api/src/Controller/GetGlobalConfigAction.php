<?php

declare(strict_types=1);

namespace App\Controller;

use App\ConfigurationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/config", name="global_config")
 */
final class GetGlobalConfigAction extends AbstractController
{
    private ConfigurationManager $configurationManager;

    public function __construct(ConfigurationManager $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    public function __invoke(Request $request): Response
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept',
        ];
        if ('OPTIONS' === $request->getMethod()) {
            return new JsonResponse(null, 204, $corsHeaders);
        }

        $response = new JsonResponse($this->configurationManager->getArray(), 200, $corsHeaders);
        $response->setCache([
            's_maxage' => 600,
            'max_age' => 600,
            'public' => true,
        ]);

        return $response;
    }
}
