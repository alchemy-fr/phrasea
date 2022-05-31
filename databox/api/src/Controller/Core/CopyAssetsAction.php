<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Api\IriConverterInterface;
use App\Asset\AssetCopier;
use App\Consumer\Handler\Asset\AssetCopyHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CopyAssetsAction extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;
    private IriConverterInterface $iriConverter;

    public function __construct(
        EventProducer $eventProducer,
        EntityManagerInterface $em,
        IriConverterInterface $iriConverter
    ) {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
        $this->iriConverter = $iriConverter;
    }

    public function __invoke(Asset $data, Request $request)
    {
        $data = $data->copyAction;

        /** @var RemoteUser $user */
        $user = $this->getUser();
        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($data->ids);

        $dest = $this->iriConverter->getItemFromIri($data->destination);
        $this->denyAccessUnlessGranted(CollectionVoter::EDIT, $dest);

        $options = [];
        if ($data->withAttributes) {
            $options[AssetCopier::OPT_WITH_ATTRIBUTES] = true;
        }
        if ($data->withTags) {
            $options[AssetCopier::OPT_WITH_TAGS] = true;
        }

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AssetVoter::READ, $asset);
            $symlink = $data->byReference && $this->isGranted(AssetVoter::EDIT, $asset);

            $this->eventProducer->publish(AssetCopyHandler::createEvent(
                $user->getId(),
                $asset->getId(),
                $data->destination,
                $symlink,
                $options
            ));
        }

        return new Response('', 204);
    }
}
