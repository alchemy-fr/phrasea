<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Security\Voter\PublicationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        if (in_array(Asset::GROUP_READ, $context['groups'] ?? [])) {
            $publication = $object->getPublication();
            $isAuthorized = $this->security->isGranted(PublicationVoter::READ_DETAILS, $publication);
            $publication->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = ['_'];

                return;
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
        $poster = $object->getPosterDefinition();

        $thumbObject = $object->getThumbnailDefinition() ?? $poster ?? $object->getPreviewDefinition() ?? $object;
        if (str_starts_with($thumbObject->getMimeType(), 'image/')) {
            $object->setThumbUrl($this->generateAssetUrl($thumbObject));
        }
        $object->setPreviewUrl($this->generateAssetUrl($object->getPreviewDefinition() ?? $object));
        if (null !== $poster) {
            $object->setPosterUrl($this->generateAssetUrl($poster));
        }

        $isPublic = $object->getPublication()->isPublic();

        if (!empty($webVTTs = $object->getWebVTT())) {
            $links = [];
            foreach ($webVTTs as $webVTT) {
                $vttUrl = $this->urlGenerator->generate('asset_webvtt', [
                    'id' => $object->getId(),
                    'vttId' => $webVTT['id'],
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                if (!$isPublic) {
                    $vttUrl = $this->uriJwtManager->signUri($vttUrl);
                }

                $links[] = [
                    'locale' => $webVTT['locale'],
                    'label' => $webVTT['label'] ?? $webVTT['locale'],
                    'url' => $vttUrl,
                    'id' => $webVTT['id'],
                    'kind' => $webVTT['kind'] ?? 'subtitles',
                ];
            }
            $object->setWebVTTLinks($links);
        }
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
