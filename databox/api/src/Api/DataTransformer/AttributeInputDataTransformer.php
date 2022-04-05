<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AttributeInput;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeInputDataTransformer extends AbstractInputDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param AttributeInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Attribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);

            $definition = null;
            if ($data->definition) {
                $definition = $data->definition;
            } elseif ($data->name && $object->getAsset()) {
                $definition = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
                    'name' => $data->name,
                    'workspace' => $object->getAsset()->getWorkspace()->getId(),
                ]);

                if (!$definition instanceof AttributeDefinition) {
                    throw new BadRequestHttpException(sprintf('Attribute definition "%s" not found', $data->name));
                }
            }

            if ($definition instanceof AttributeDefinition) {
                $object->setDefinition($definition);
            }
        }

        if ($data->origin) {
            if (false !== $k = array_search($data->origin, Attribute::ORIGIN_LABELS, true)) {
                $object->setOrigin($k);
            }
        }
        if ($data->status) {
            if (false !== $k = array_search($data->status, Attribute::STATUS_LABELS, true)) {
                $object->setStatus($k);
            }
        }

        if ($data->locale) {
            $object->setLocale($data->locale);
        }
        $object->setValue($data->value);
        $object->setOriginUserId($data->originUserId);
        $object->setOriginVendor($data->originVendor);
        $object->setOriginVendorContext($data->originVendorContext);
        $object->setPosition($data->position ?? 0);
        if ($data->confidence) {
            $object->setConfidence($data->confidence);
        }
        $object->setCoordinates($data->coordinates);

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Attribute) {
            return false;
        }

        return Attribute::class === $to && AttributeInput::class === ($context['input']['class'] ?? null);
    }
}
