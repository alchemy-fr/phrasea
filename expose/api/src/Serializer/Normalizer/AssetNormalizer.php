<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class AssetNormalizer extends AbstractRouterNormalizer
{
    public function __construct(private readonly Security $security)
    {
    }
    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        if (in_array(Asset::GROUP_READ, $context['groups'])) {
            $publication = $object->getPublication();
            $isAuthorized = $this->security->isGranted(PublicationVoter::READ_DETAILS, $publication);
            $publication->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = ['_'];
            }
        }
        
        $downloadViaEmail = $context['download_via_email'] ?? false;

        if (!$downloadViaEmail) {
            $object->setDownloadUrl($this
                ->generateDownloadAssetTrackerUrl($object));
        } else {
            $object->setDownloadUrl($this->getDownloadViaEmailUrl($object));
        }

        $object->setUrl($this->generateAssetUrl($object));
        $object->setThumbUrl($this->generateAssetUrlOrVideoPreviewUrl($object->getThumbnailDefinition() ?? $object->getPreviewDefinition() ?? $object));
        $object->setPreviewUrl($this->generateAssetUrlOrVideoPreviewUrl($object->getPreviewDefinition() ?? $object));

        if (!empty($webVTT = $object->getWebVTT())) {
            $object->setWebVTTLink($this->urlGenerator->generate('asset_webvtt', [
                'id' => $object->getId(),
                'hash' => md5($webVTT),
            ], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
