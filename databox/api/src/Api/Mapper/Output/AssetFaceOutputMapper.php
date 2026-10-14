<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\AssetFaceOutput;
use App\Entity\Core\AssetFace;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AssetFaceOutput::class)]
final class AssetFaceOutputMapper implements OutputMapperInterface
{
    public function supports(object $data): bool
    {
        return $data instanceof AssetFace;
    }

    /**
     * @param AssetFace $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AssetFaceOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->asset = $data->getAsset();
        $output->box = $data->getBox();
        $output->position = $data->getPosition();
        $output->confidence = $data->getConfidence();
        $output->identity = $data->getIdentity();
        $output->identityConfidence = $data->getIdentityConfidence();
        $output->identityOrigin = $data->getIdentityOrigin();
        $output->reference = $data->getReference()?->getId();

        $details = $data->getDetails() ?? [];
        $output->age = isset($details['age']) ? (int) $details['age'] : null;
        $output->gender = isset($details['gender']) ? (string) $details['gender'] : null;

        return $output;
    }
}
