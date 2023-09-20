<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\CopyAssetInput;
use App\Asset\AssetCopier;
use App\Consumer\Handler\Asset\AssetCopyHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class CopyAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EventProducer $eventProducer,
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

            $this->eventProducer->publish(AssetCopyHandler::createEvent(
                $user->getId(),
                $user->getGroupIds(),
                $asset->getId(),
                $data->destination,
                $symlink,
                $options
            ));
        }

        return new Response('', 204);
    }
}
