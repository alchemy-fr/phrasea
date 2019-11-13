<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\SubDefinition;

class SubDefinitionNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param SubDefinition $object
     */
    public function normalize($object, array &$context = [])
    {
        $object->setUrl($this->generateSubDefinitionUrl('asset_subdef_open', $object));
        $object->setDownloadUrl($this->generateSubDefinitionUrl('asset_subdef_download', $object));
    }

    public function support($object, $format): bool
    {
        return $object instanceof SubDefinition;
    }
}
