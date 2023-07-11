<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\DataTransformer\AssetOutputDataTransformer;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\MultipleAssetOutput;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Bundle\SecurityBundle\Security;

class MultipleAssetCreate
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly AssetOutputDataTransformer $assetOutputDataTransformer, private readonly Security $security)
    {
    }

    public function __invoke($data)
    {
        foreach ($data as $asset) {
            $this->em->persist($asset);
        }

        $this->em->flush();

        $output = new MultipleAssetOutput();
        $output->assets = array_map(function (Asset $asset): AssetOutput {
            if (!$this->security->isGranted(AbstractVoter::CREATE, $asset)) {
                throw new AccessDeniedHttpException();
            }

            return $this->assetOutputDataTransformer->transform($asset, AssetOutput::class, [
                'groups' => [
                    '_',
                    'asset:read',
                ],
            ]);
        }, $data);

        return $output;
    }
}
