<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\CopyAssetInput;
use App\Asset\AssetCopier;
use App\Consumer\Handler\Asset\AssetCopy;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class CopyAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;
    use WithOwnerIdProcessorTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    /**
     * @param CopyAssetInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        /** @var JwtUser $user */
        $user = $this->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : $data->getOwnerId();
        $userGroups = $user instanceof JwtUser ? $user->getGroups() : [];

        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($data->ids);

        $dest = $this->iriConverter->getResourceFromIri($data->destination);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $dest);

        $options = [];
        if ($data->withAttributes) {
            $options[AssetCopier::OPT_WITH_ATTRIBUTES] = true;
        }
        if ($data->withTags) {
            $options[AssetCopier::OPT_WITH_TAGS] = true;
        }

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
            $symlink = $data->byReference && $this->isGranted(AbstractVoter::EDIT, $asset);

            $this->bus->dispatch(new AssetCopy(
                $userId,
                $userGroups,
                $asset->getId(),
                $data->destination,
                $symlink,
                $options
            ));
        }

        return new Response('', 204);
    }
}
