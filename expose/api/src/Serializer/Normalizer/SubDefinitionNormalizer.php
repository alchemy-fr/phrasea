<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\SubDefinition;

class SubDefinitionNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param SubDefinition $object
     */
    public function normalize($object, array &$context = []): void
    {
        $object->setUrl($this->generateSubDefinitionUrl($object));

        $downloadViaEmail = $context['download_via_email'] ?? false;

        if (!$downloadViaEmail) {
            $object->setDownloadUrl($this
                ->generateDownloadSubDefTrackerUrl($object));
        } else {
            $object->setDownloadUrl($this->getDownloadViaEmailUrl($object->getAsset(), $object->getId()));
        }
    }

    public function support($object): bool
    {
        return $object instanceof SubDefinition;
    }
}
