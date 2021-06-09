<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;

class SubDefinitionNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param SubDefinition $object
     */
    public function normalize($object, array &$context = []): void
    {
        $object->setUrl($this->generateSubDefinitionUrl($object));

        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $context['publication_asset'] ?? null;
        $downloadViaEmail = $context['download_via_email'] ?? false;

        if ($publicationAsset instanceof PublicationAsset) {
            if (!$downloadViaEmail) {
                $object->setDownloadUrl($this
                    ->generateDownloadSubDefTrackerUrl(
                        $publicationAsset->getPublication(),
                        $object,
                    ));
            } else {
                $object->setDownloadUrl($this->getDownloadViaEmailUrl($publicationAsset, $object->getId()));
            }
        }
    }

    public function support($object): bool
    {
        return $object instanceof SubDefinition;
    }
}
