<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\UploadLink;
use App\Security\Voter\TargetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The link belongs to the user creating it, who must be able to upload to its target.
 */
final readonly class CreateUploadLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    /**
     * @param UploadLink $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UploadLink
    {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        if (!$this->security->isGranted(TargetVoter::UPLOAD, $data->getTarget())) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        $data->setOwnerId($user->getId());

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
