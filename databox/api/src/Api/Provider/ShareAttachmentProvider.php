<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\Share;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\FileUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShareAttachmentProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly ShareRepository $shareRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $item = $this->shareRepository->find($uriVariables['id']);
        if (!$item instanceof Share) {
            return $this->createNotFoundResponse();
        }

        if (!$this->security->isGranted(AbstractVoter::READ, $item)) {
            return $this->createNotFoundResponse();
        }

        $attachment = $this->em->find(AssetAttachment::class, $uriVariables['attachment']);
        if (!$attachment instanceof AssetAttachment) {
            return $this->createNotFoundResponse();
        }

        $belongsToShare = false;
        foreach ($item->getAssetsList() as $asset) {
            if ($attachment->getAsset()?->getId() === $asset->getId()) {
                $belongsToShare = true;
                break;
            }
        }

        $file = $attachment->getAttachment()?->getSource();
        if (!$belongsToShare || null === $file) {
            return $this->createNotFoundResponse();
        }

        return new RedirectResponse($this->fileUrlResolver->resolveUrl($file));
    }

    private function createNotFoundResponse(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
}
