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
        // None without the details of the publication or downloads disabled
        $object->setDownloadUrl($this->generateSubDefinitionDownloadUrl($object));

        if ($this->getDownloadMode($object->getAsset()->getPublication())->exposesFiles()) {
            $object->setUrl($this->generateSubDefinitionUrl($object));
        }
    }

    public function support($object): bool
    {
        return $object instanceof SubDefinition;
    }
}
