<?php

namespace Alchemy\CoreBundle\Api;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model;

readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private string $applicationName,
        private string $applicationId,
    )
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        /** @var OpenApi $openApi */
        $openApi = $this->decorated->__invoke($context);

        $apiTitle = sprintf('%s API', ucfirst($this->applicationName));
        if ($this->applicationId && $this->applicationId !== $this->applicationName) {
            $apiTitle .= sprintf(' (%s)', $this->applicationId);
        }

        $openApi = $openApi
            ->withInfo(
                (new Model\Info($apiTitle, $openApi->getInfo()->getVersion()))
            )
        ;

        /** @var OpenApi $openApi */
        $openApi = $openApi->withServers([]);

        return $openApi;
    }
}
