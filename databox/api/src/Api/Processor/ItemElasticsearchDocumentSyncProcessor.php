<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\Response;

class ItemElasticsearchDocumentSyncProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly DeferredIndexListener $deferredIndexListener,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $this->denyAccessUnlessGranted(JwtUser::ROLE_TECH);
        $this->deferredIndexListener->scheduleForUpdate($data);

        return new Response('', 201);
    }
}
