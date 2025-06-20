<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Provider\ShareReadProvider;
use App\Entity\Core\Share;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ShareProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;
    use WithOwnerIdProcessorTrait;

    public function __construct(
        private readonly ShareReadProvider $shareReadProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $decorated,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param Share $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Share
    {
        $item = $this->decorated->process($this->processOwnerId($data), $operation, $uriVariables, $context);
        $this->validator->validate($item);

        return $this->shareReadProvider->provideShare($item);
    }
}
