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
    public function normalize($object, array &$context = [])
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $context['publication_asset'] ?? null;
        if ($publicationAsset instanceof PublicationAsset) {
            $object->setUrl($this->generateSubDefinitionUrl('asset_subdef_open', $publicationAsset, $object));
            $object->setDownloadUrl($this->generateSubDefinitionUrl('asset_subdef_download', $publicationAsset, $object));
        }
    }

    public function support($object, $format): bool
    {
        return $object instanceof SubDefinition;
    }
}
