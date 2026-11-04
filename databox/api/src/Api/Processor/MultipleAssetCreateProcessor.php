<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Mapper\Input\MultipleAssetInputMapper;
use App\Api\Model\Input\MultipleAssetInput;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MultipleAssetCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private MultipleAssetInputMapper $multipleAssetInputMapper,
    ) {
    }

    /**
     * @param MultipleAssetInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipleAssetOutput
    {
        $assets = $this->multipleAssetInputMapper->map($data, $context + ['operation' => $operation]);

        foreach ($assets as $asset) {
            if (!$this->security->isGranted(AbstractVoter::CREATE, $asset)) {
                throw new AccessDeniedHttpException();
            }
            $this->em->persist($asset);
        }

        $this->em->flush();

        $output = new MultipleAssetOutput();
        $output->assets = $assets;

        return $output;
    }
}
