<?php

namespace Alchemy\AuthBundle\Api\Processor;

use Alchemy\AuthBundle\Api\Resource\OneTimeToken;
use Alchemy\AuthBundle\Security\OneTimeTokenAuthenticator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

readonly class UserOneTimeTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private OneTimeTokenAuthenticator $oneTimeTokenAuthenticator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OneTimeToken
    {
        $ott = new OneTimeToken();
        $ott->setToken($this->oneTimeTokenAuthenticator->createToken());

        return $ott;
    }
}
