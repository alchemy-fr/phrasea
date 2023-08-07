<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\InputTransformer\MultipleAssetInputTransformer;
use App\Api\Model\Input\MultipleAssetInput;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Api\OutputTransformer\AssetOutputTransformer;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MultipleAssetCreate
{
    public function __construct(
        private EntityManagerInterface $em,
        private AssetOutputTransformer $assetOutputDataTransformer,
        private MultipleAssetInputTransformer $inputTransformer,
        private Security $security
    ) {
    }

    public function __invoke(MultipleAssetInput $data): MultipleAssetOutput
    {
        /** @var array $assets */
        $assets = $this->inputTransformer->transform($data, Asset::class);
        foreach ($assets as $asset) {
            $this->em->persist($asset);
        }

        $this->em->flush();

        $output = new MultipleAssetOutput();
        $output->assets = array_map(function (Asset $asset): AssetOutput {
            if (!$this->security->isGranted(AbstractVoter::CREATE, $asset)) {
                throw new AccessDeniedHttpException();
            }

            $context = [
                'groups' => [
                    '_',
                    Asset::GROUP_READ,
                ],
            ];

            return $this->assetOutputDataTransformer->transform($asset, AssetOutput::class, $context);
        }, $assets);

        return $output;
    }
}
