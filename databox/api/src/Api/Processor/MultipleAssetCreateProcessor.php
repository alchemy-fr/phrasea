<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MultipleAssetCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    /**
     * @param Asset[] $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipleAssetOutput
    {
        foreach ($data as $asset) {
            if (!$this->security->isGranted(AbstractVoter::CREATE, $asset)) {
                throw new AccessDeniedHttpException();
            }
            $this->em->persist($asset);
        }

        $this->em->flush();

        $output = new MultipleAssetOutput();
        $output->assets = $data;

        return $output;
    }
}
