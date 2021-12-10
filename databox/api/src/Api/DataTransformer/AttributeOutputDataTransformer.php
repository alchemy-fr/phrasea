<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AttributeOutput;
use App\Entity\Core\Attribute;

class AttributeOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param Attribute $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AttributeOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->value = $object->getValue();
        $output->origin = $object->getOriginLabel();
        $output->originUserId = $object->getOriginUserId();
        $output->originVendor = $object->getOriginVendor();
        $output->originVendorContext = $object->getOriginVendorContext();
        $output->definition = $object->getDefinition();
        $output->status = $object->getStatusLabel();
        $output->confidence = $object->getConfidence();
        $output->coordinates = $object->getCoordinates();
        $output->locale = $object->getLocale();

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeOutput::class === $to && $data instanceof Attribute;
    }
}