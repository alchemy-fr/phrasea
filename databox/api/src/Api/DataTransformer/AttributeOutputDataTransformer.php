<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AttributeOutput;
use App\Asset\Attribute\AttributesResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;

class AttributeOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private AttributesResolver $attributesResolver;

    public function __construct(AttributesResolver $attributesResolver)
    {
        $this->attributesResolver = $attributesResolver;
    }

    /**
     * @param Attribute $object
     */
    public function transform($object, string $to, array $context = [])
    {
        /** @var Asset $asset */
        $locale = $object->getLocale() ?? '_';
        $attributes = $this->attributesResolver->resolveAttributes($object->getAsset(), true);
        if (isset($attributes[$object->getDefinition()->getId()][$locale])) {
            $object = $attributes[$object->getDefinition()->getId()][$locale];
        }

        $output = new AttributeOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->asset = $object->getAsset();
        $output->value = $object->getValues() ?? $object->getValue();
        $output->fallbackValue = $object->getFallbackValues() ?? $object->getFallbackValue();
        $output->highlight = $object->getHighlights() ?? $object->getHighlight();
        $output->origin = $object->getOriginLabel();
        $output->originUserId = $object->getOriginUserId();
        $output->originVendor = $object->getOriginVendor();
        $output->originVendorContext = $object->getOriginVendorContext();
        $output->status = $object->getStatusLabel();
        $output->confidence = $object->getConfidence();
        $output->coordinates = $object->getCoordinates();
        $output->locale = $object->getLocale();
        $output->definition = $object->getDefinition();
        $output->position = $object->getPosition();

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeOutput::class === $to && $data instanceof Attribute;
    }
}
