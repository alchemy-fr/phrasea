<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\UploadLink;
use App\Security\UploadLinkCreatorChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Gives the link a new token: the URL handed out so far stops working, the uploads made with it stay.
 */
final readonly class RegenerateUploadLinkTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private UploadLinkCreatorChecker $creatorChecker,
    ) {
    }

    /**
     * @param UploadLink $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UploadLink
    {
        $data->regenerateToken();

        $user = $this->security->getUser();
        if ($user instanceof JwtUser && $user->getId() === $data->getOwnerId()) {
            $this->creatorChecker->snapshot($data, $user);
        }

        $this->em->flush();

        return $data;
    }
}
