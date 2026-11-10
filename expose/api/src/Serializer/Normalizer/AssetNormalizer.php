<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ResetInterface;

class AssetNormalizer extends AbstractRouterNormalizer implements ResetInterface
{
    /**
     * READ_DETAILS of each publication for the current token: every asset of a publication
     * asks it again.
     *
     * @var \WeakMap<Publication, bool>
     */
    private \WeakMap $readDetails;
    private ?TokenInterface $readDetailsToken = null;

    public function __construct(private readonly Security $security)
    {
        $this->readDetails = new \WeakMap();
    }

    public function reset(): void
    {
        $this->readDetails = new \WeakMap();
        $this->readDetailsToken = null;
    }

    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        $publication = $object->getPublication();
        if (in_array(Asset::GROUP_READ, $context['groups'] ?? [])) {
            $isAuthorized = $this->canReadDetails($publication);
            $publication->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = ['_'];

                return;
            }
        } elseif (!$this->canReadDetails($publication)) {
            // The cover of a listed publication the visitor cannot open: its thumbnail only
            $thumbnail = $object->getThumbnailDefinition() ?? $object->getPosterDefinition();
            if (null !== $thumbnail && str_starts_with($thumbnail->getMimeType(), 'image/')) {
                $object->setThumbUrl($this->generateAssetUrl($thumbnail));
                $object->setThumbType($thumbnail->getMimeType());
            }

            return;
        }

        $object->setDownloadUrl($this->generateAssetDownloadUrl($object));

        // The original file is given only to whom may download it right away.
        $original = $this->getDownloadMode($publication)->exposesFiles() ? $object : null;
        if (null !== $original) {
            $object->setUrl($this->generateAssetUrl($original));
        }
        $poster = $object->getPosterDefinition();

        $thumbObject = $object->getThumbnailDefinition() ?? $poster ?? $object->getPreviewDefinition() ?? $original;
        if (null !== $thumbObject && str_starts_with($thumbObject->getMimeType(), 'image/')) {
            $object->setThumbUrl($this->generateAssetUrl($thumbObject));
            $object->setThumbType($thumbObject->getMimeType());
        }
        $previewObject = $object->getPreviewDefinition() ?? $original;
        if (null !== $previewObject) {
            $object->setPreviewUrl($this->generateAssetUrl($previewObject));
            $object->setPreviewType($previewObject->getMimeType());
        }
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

    private function canReadDetails(Publication $publication): bool
    {
        $token = $this->security->getToken();
        if ($token !== $this->readDetailsToken) {
            $this->reset();
            $this->readDetailsToken = $token;
        }

        return $this->readDetails[$publication] ??= $this->security->isGranted(PublicationVoter::READ_DETAILS, $publication);
    }
}
