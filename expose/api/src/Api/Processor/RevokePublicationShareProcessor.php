<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PublicationShare;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RevokePublicationShareProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param PublicationShare $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicationShare
    {
        $user = $this->security->getUser();
        $data->revoke($user instanceof JwtUser ? $user->getId() : null);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
