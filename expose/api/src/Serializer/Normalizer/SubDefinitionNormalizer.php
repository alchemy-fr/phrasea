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
        $object->setDownloadUrl($this->generateSubDefinitionUrl($object, true));
    }

    public function support($object): bool
    {
        return $object instanceof SubDefinition;
    }
}
